<?php

namespace App\Model\Behavior;

use Cake\ORM\Behavior;
use Cake\ORM\Query;
use Cake\Database\Expression\ValuesExpression;
use Cake\ORM\TableRegistry;
use Cake\Event\Event;
use Cake\Datasource\EntityInterface;
use ArrayObject;
use Cake\Utility\Hash;
use InvalidArgumentException;

/**
 * Hierarchy behavior
 */
class ClosureTableBehavior extends Behavior
{
    /**
     * Default configuration.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'implementedFinders' => [
            'path' => 'findParents',
            'parents' => 'findParents',
            'children' => 'findChildren',
            'child' => 'findChild',
            'branches' => 'findBranches',
            'branch' => 'findBranch',
            'leaves' => 'findLeaves',
            'leaf' => 'findLeaf',
            'siblings' => 'findSiblings',
            'sibling' => 'findSibling',
        ],
        'implementedMethods' => [
            'getDepth' => 'getDepth',
            'getOrder' => 'getOrder',
            'count' => 'count',
            'countParents' => 'getDepth',
            'countChildren' => 'countChildren',
            'has' => 'has',
            'hasParent' => 'hasParent',
            'hasChildren' => 'hasChildren',
            'move' => 'move',
            'changeParent' => 'changeParent', // needs test
            'changeOrder' => 'changeOrder', //bo be implemented
            'importTree' => 'importTree', // needs test
        ],
        'tableName' => null, //Table name for a table storing closure table data
        'parentField' => 'parent_id' // column name for parent id in a base table
    ];

    private $__table;

    private $closureTable;

    public function initialize(array $config)
    {
        if (empty($config) || !isset($config['tableName'])) {
            $config['tableName'] = $this->_table->getRegistryAlias() . 'Closure';
        }

        $this->config = Hash::merge($this->_defaultConfig, $config);
        $this->closureTable = $this->config['tableName'];

        $this->createAssociations();
    }

    public function afterSave(Event $event, EntityInterface $entity, ArrayObject $options)
    {
        if ($entity->isNew()) {
            $rows = [];

            // Entity is new root node
            if ($entity->{$this->config['parentField']} === null) {
                $lastChild = $this->__table->find()
                    ->select(['node_order'])
                    ->where([
                        "parent_id IS" => null,
                        "depth" => 1
                    ])
                    ->order(["node_order" => 'DESC'])
                    ->enableHydration(false)
                    ->first();

                $rows[] = [
                    'parent_id' => null,
                    'child_id' => $entity->get($this->_table->getPrimaryKey()),
                    'depth' => 1,
                    'node_order' => $lastChild !== null ? $lastChild['node_order'] + 1 : 1
                ];
            } else {
                // Entity is new child node.
                $parents = $this->_table->find('parents', [
                    'for' => $entity->get($this->config['parentField']),
                    'withSelf' => true
                ])->cleanCopy();

                $parents->order(["{$this->_table->getAlias()}.parent_id" => 'ASC']);
                $parents = $parents->select(['id', 'parent_id'])->enableHydration(false)->toArray();

                // Distance from most top element
                $rows[] = [
                    'parent_id' => null,
                    'child_id' => $entity->get($this->_table->getPrimaryKey()),
                    'depth' => count($parents) + 1,
                    'node_order' => null,
                ];

                $lastChild = $this->__table->find()
                    ->select(['node_order'])
                    ->where([
                        "parent_id" => $entity->get($this->config['parentField']),
                        "depth" => 1
                    ])
                    ->order(["node_order" => 'DESC'])
                    ->enableHydration(false)
                    ->first();

                foreach ($parents as $i => $parent) {
                    $order = null;
                    if ($i === count($parents) - 1) {
                        $order = $lastChild !== null ? $lastChild['node_order'] + 1 : 1;
                    }

                    $rows[] = [
                        'parent_id' => $parent['id'],
                        'child_id' => $entity->get($this->_table->getPrimaryKey()),
                        'depth' => count($parents) - $i,
                        'node_order' => $order
                    ];
                }
            }

            $rows[] = [
                'parent_id' => $entity->get($this->_table->getPrimaryKey()),
                'child_id' => $entity->get($this->_table->getPrimaryKey()),
                'depth' => 0,
                'node_order' => null
            ];

            $this->insertData($rows);

        } elseif ($entity->isDirty($this->config['parentField'])) {
            $this->changeParent($entity->get($this->_table->getPrimaryKey()), $entity->{$this->config['parentField']});
        }

        return true;
    }

    /**
     * Nests given node under new parent.
     *
     * @param $id integer Id of node to be nested.
     * @param $newParentId integer Id of new parent node.
     */
    private function changeParent($id, $newParentId)
    {
        $tree = $this->findParents($this->_table->find(), ['for' => $newParentId, 'withSelf' => true])->find('threaded')->toArray();
        $children = $this->findChildren($this->_table->find(), ['for' => $id, 'withSelf' => true])->find('threaded')->toArray();

        $hierarchy = [];
        $this->nest($tree, $children, $newParentId);
        $this->buildHierarchy($tree, $hierarchy, $newParentId);

        $rows = $this->buildInsertData($hierarchy);

        $toDelete = $this->__table->find('list', [
            'keyField' => 'child_id',
            'valueField' => 'child_id'
        ])->where([
            'parent_id' => $id
        ]);

        $query = $this->__table->query();
        $query->delete()
            ->where(['child_id IN' => $toDelete])
            ->execute();

        $this->insertData($rows);
    }

    /**
     * Function to import a tree.
     */
    public function importTree()
    {
        $tree = $this->_table->find('threaded')->select([
            $this->_table->getPrimaryKey(),
            $this->config['parentField']
        ])->toArray();

        $stack = [];
        $this->buildHierarchy($tree, $stack, null);
        $rows = $this->buildInsertData($stack);

        $this->insertData($rows);
    }

    /**
     * Get all parents for a given parent node.
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the node for which parents will be found.
     * - withSelf: Boolean, allows to include / exclude node for which parents will be found.
     *
     * @param Query $query
     * @param array $options
     * @return Query
     * @throws InvalidArgumentException When the 'for' key is not passed in $options
     */
    public function findParents(Query $query, array $options)
    {
        if (empty($options['for'])) {
            throw new InvalidArgumentException("The 'for' key is required for find('parents').");
        }

        $query->innerJoinWith("{$this->closureTable}Parents", function ($q) use ($options) {
            return $q->where(["{$this->closureTable}Parents.child_id" => $options['for']]);
        });

        if (!isset($options['withSelf']) || !$options['withSelf']) {
            $query->andWhere(["{$this->closureTable}Parents.depth IS NOT" => 0]);
        }

        $query->order(['depth' => 'DESC']);

        return $query;
    }

    /**
     * Get all children for a given parent node.
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the node for which children will be found.
     * - withSelf: Boolean, allows to include / exclude node for which children will be found.
     *
     * @param Query $query
     * @param array $options
     * @return Query
     * @throws InvalidArgumentException When the 'for' key is not passed in $options
     */
    public function findChildren(Query $query, array $options)
    {
        // TODO add option to find Direct children
        if (empty($options['for'])) {
            throw new InvalidArgumentException("The 'for' key is required for find('children').");
        }

        if (array_key_exists('node_order', $options)) {
            $this->findChildrenRange($query, $options);
        } else {
            $this->getChildren($query, $options);
        }

        return $query;
    }

    /**
     * Returns query finding children in positions range (order).
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the parent node.
     * - startPos: Integer, initial position (node order) to find child at.
     * - endPos: Integer, final position (node order) to find child at.
     *
     * Only one of position is required.
     *
     * @param Query $query
     * @param array $options
     * @throws InvalidArgumentException When the 'for' key is not passed in $options
     */
    protected function findChildrenRange(Query $query, array $options)
    {
        if (
            (!isset($options['node_order']) || !is_array($options['node_order']))
            || ((empty($options['node_order']['from']) || !is_numeric($options['node_order']['from']))
                && (empty($options['node_order']['to']) || !is_numeric($options['node_order']['to']))
            )
        ) {
            throw new InvalidArgumentException("The 'node_order[\'from\']' or 'node_order[\'to\']' key is required for find('findChildren').");
        }

        $this->getChildren($query, $options);
        $query->andWhere(["{$this->closureTable}Children.depth" => 1]);

        if (isset($options['node_order']['from'])) {
            $query->andWhere(["{$this->closureTable}ChildrenInOrder.node_order >=" => $options['node_order']['from']]);
        }

        if (isset($options['node_order']['to'])) {
            $query->andWhere(["{$this->closureTable}ChildrenInOrder.node_order <=" => $options['node_order']['to']]);
        }
    }

    public function findChild(Query $query, array $options)
    {
        if (empty($options['for'])) {
            throw new InvalidArgumentException("The 'for' key is required for find('child').");
        }

        if (isset($options['get'])) {
            switch ($options['get']) {
                case 'first':
                    $this->getFirstChild($query, $options);
                    break;
                case 'last':
                    $this->getLastChild($query, $options);
                    break;
                default:
                    throw new InvalidArgumentException("Invalid 'get' key for find('child').");
            }
        } else {
            $this->getChildAt($query, $options);
        }

        return $query;
    }

    /**
     * Returns query finding first child for given parent.
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the parent node.
     *
     * @param Query $query
     * @param array $options
     */
    protected function getFirstChild(Query $query, array $options)
    {
        $this->getChildren($query, $options);
        $query->andWhere(["{$this->closureTable}ChildrenInOrder.node_order" => 1])->limit(1);
    }

    /**
     * Returns query finding last child for given parent.
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the parent node.
     *
     * @param Query $query
     * @param array $options
     */
    protected function getLastChild(Query $query, array $options)
    {
        $this->getChildren($query, $options);
        $query->andWhere(["{$this->closureTable}Children.depth" => 1])->order([
            "{$this->closureTable}ChildrenInOrder.node_order" => 'DESC'
        ], true)->limit(1);
    }

    /**
     * Returns query finding child at given position (node order).
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the parent node.
     * - pos: Integer, position (node order) to find child at.
     *
     * @param Query $query
     * @param array $options
     * @throws InvalidArgumentException When the 'for' key is not passed in $options
     */
    protected function getChildAt(Query $query, array $options)
    {
        foreach (['for', 'node_order'] as $key) {
            if (empty($options[$key]) || !is_numeric($options[$key])) {
                throw new InvalidArgumentException("The '{$key}' key is required for find('child').");
            }
        }

        $this->getChildren($query, $options);
        $query->andWhere([
            "{$this->closureTable}Children.node_order" => $options['node_order'],
            "{$this->closureTable}Children.depth" => 1
        ]);
    }

    /**
     * Get all branches for a given parent node.
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the parent node for which branches will be found.
     *
     * @param Query $query
     * @param array $options
     * @return Query
     * @throws InvalidArgumentException When the 'for' key is not passed in $options
     */
    public function findBranches(Query $query, array $options)
    {
        if (empty($options['for'])) {
            throw new InvalidArgumentException("The 'for' key is required for find('branches').");
        }

        if (array_key_exists('get', $options)) {
            switch ($options['get']) {
                case 'prev':
                    $this->getPrevBranches($query, $options);
                    break;
                case 'next':
                    $this->getNextBranches($query, $options);
                    break;
                case 'siblings':
                    $this->getSiblingBranches($query, $options);
                    break;
                default:
                    throw new InvalidArgumentException("Invalid 'get' key for find('branches').");
            }
        } else {
            $this->getBranches($query, $options);
            $query->order(["{$this->closureTable}Children.node_order" => 'ASC']);
        }

        return $query;
    }

    /**
     * Get first leaf having order higher then given node.
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the node for which branch will be found.
     *
     * @param Query $query
     * @param array $options
     * @throws InvalidArgumentException When the 'for' key is not passed in $options
     */
    protected function getPrevBranches(Query $query, array $options)
    {
        $node = $this->__table->find()
            ->select(['parent_id', 'node_order'])
            ->where(['child_id' => $options['for'], 'depth' => 1])
            ->first();

        $options['for'] = -1; //FIXME

        if (!empty($node)) {
            $options['for'] = $node->parent_id;
            $options['_branch'] = ['dir' => '<', 'order' => $node->node_order];
        }

        $this->getBranches($query, $options);
        $query->order(["{$this->closureTable}Children.node_order" => 'DESC']);
    }

    /**
     * Get all branches having order higher then given node.
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the node for which branches will be found.
     *
     * @param Query $query
     * @param array $options
     * @throws InvalidArgumentException When the 'for' key is not passed in $options
     */
    protected function getNextBranches(Query $query, array $options)
    {
        $node = $this->__table->find()
            ->select(['parent_id', 'node_order'])
            ->where(['child_id' => $options['for'], 'depth' => 1])
            ->first();

        $options['for'] = -1; //FIXME

        if (!empty($node)) {
            $options['for'] = $node->parent_id;
            $options['_branch'] = ['dir' => '>', 'order' => $node->node_order];
        }

        $this->getBranches($query, $options);
        $query->order(["{$this->closureTable}Children.node_order" => 'ASC']);
    }

    /**
     * Get all branches having order higher then given node.
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the node for which branches will be found.
     *
     * @param Query $query
     * @param array $options
     * @throws InvalidArgumentException When the 'for' key is not passed in $options
     */
    protected function getSiblingBranches(Query $query, array $options)
    {
        $for = $options['for'];

        $node = $this->__table->find()
            ->select(['parent_id', 'node_order', 'id', 'child_id'])
            ->where(['child_id' => $options['for'], 'depth' => 1])
            ->first();

        $options['for'] = -1; //FIXME

        if (!empty($node)) {
            $options['for'] = $node->parent_id;
        }

        $this->getBranches($query, $options);
        $query->andWhere(["{$this->closureTable}Children.child_id IS NOT" => $for]);
        $query->order(["{$this->closureTable}Children.node_order" => 'ASC']);
    }

    /**
     * Get branches for a given parent node.
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the parent node for which branches will be found.
     * - get: String "first" | "last" | "prev" | "next"
     *
     * @param Query $query
     * @param array $options
     * @return Query
     * @throws InvalidArgumentException When the 'for' key is not passed in $options
     */
    public function findBranch(Query $query, array $options)
    {
        foreach (['for', 'get'] as $key) {
            if (empty($options[$key])) {
                throw new InvalidArgumentException("The '{$key}' key is required for find('branch').");
            }
        }

        switch ($options['get']) {
            case 'first':
                $this->getFirstBranch($query, $options);
                break;
            case 'last':
                $this->getLastBranch($query, $options);
                break;
            case 'prev':
                $this->getPrevBranch($query, $options);
                break;
            case 'next':
                $this->getNextBranch($query, $options);
                break;
            default:
                throw new InvalidArgumentException("Invalid 'get' key for find('branch').");
        }

        return $query;
    }

    /**
     * Get branch with lowest order for a given node.
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the parent node for which branch will be found.
     *
     * @param Query $query
     * @param array $options
     * @throws InvalidArgumentException When the 'for' key is not passed in $options
     */
    protected function getFirstBranch(Query $query, array $options)
    {
        $this->getBranches($query, $options);
        $query->order(["{$this->closureTable}Children.node_order" => 'ASC'])->limit(1);
    }

    /**
     * Get branch with highest order for a given node.
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the parent node for which branch will be found.
     *
     * @param Query $query
     * @param array $options
     * @throws InvalidArgumentException When the 'for' key is not passed in $options
     */
    protected function getLastBranch(Query $query, array $options)
    {
        $this->getBranches($query, $options);
        $query->order(["{$this->closureTable}Children.node_order" => 'DESC'])->limit(1);
    }

    /**
     * Get first branch having order lower then given node.
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the node for which branch will be found.
     *
     * @param Query $query
     * @param array $options
     * @throws InvalidArgumentException When the 'for' key is not passed in $options
     */
    protected function getPrevBranch(Query $query, array $options)
    {
        $this->getPrevBranches($query, $options);
        $query->limit(1);
    }

    /**
     * Get first branch having order higher then given node.
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the node for which which branches will be found.
     *
     * @param Query $query
     * @param array $options
     * @throws InvalidArgumentException When the 'for' key is not passed in $options
     */
    protected function getNextBranch(Query $query, array $options)
    {
        $this->getNextBranches($query, $options);
        $query->limit(1);
    }

    /**
     * Get all leaves for a given parent node.
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the parent node for which leaves will be found.
     *
     * @param Query $query
     * @param array $options
     * @return Query
     * @throws InvalidArgumentException When the 'for' key is not passed in $options
     */
    public function findLeaves(Query $query, array $options)
    {
        if (empty($options['for'])) {
            throw new InvalidArgumentException("The 'for' key is required for find('leaves').");
        }

        if (array_key_exists('get', $options)) {

            switch ($options['get']) {
                case 'prev':
                    $this->getPrevLeaves($query, $options);
                    break;
                case 'next':
                    $this->getNextLeaves($query, $options);
                    break;
                case 'siblings':
                    $this->getSiblingLeaves($query, $options);
                    break;
                default:
                    throw new InvalidArgumentException("Invalid 'get' key for find('leaves').");
            }
        } else {
            $this->getLeaves($query, $options);
            $query->order(["{$this->closureTable}Children.node_order" => 'ASC']);
        }

        return $query;
    }

    /**
     * Get all leaves having order lower then given node.
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the node for which leaves will be found.
     *
     * @param Query $query
     * @param array $options
     */
    protected function getPrevLeaves(Query $query, array $options)
    {
        $node = $this->__table->find()
            ->select(['parent_id', 'node_order'])
            ->where(['child_id' => $options['for'], 'depth' => 1])
            ->first();

        $options['for'] = -1; //FIXME

        if (!empty($node) && isset($node->parent_id)) {
            $options['for'] = $node->parent_id;
            $options['_leaf'] = ['dir' => '<', 'order' => $node->node_order];
        }

        $this->getLeaves($query, $options);
        $query->order(["{$this->closureTable}Children.node_order" => 'DESC']);
    }

    /**
     * Get all leaves having order higher then given node.
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the node for which leaves will be found.
     *
     * @param Query $query
     * @param array $options
     */
    protected function getNextLeaves(Query $query, array $options)
    {
        $node = $this->__table->find()
            ->select(['parent_id', 'node_order'])
            ->where(['child_id' => $options['for'], 'depth' => 1])
            ->first();

        $options['for'] = -1; //FIXME

        if (!empty($node) && isset($node->parent_id)) {
            $options['for'] = $node->parent_id;
            $options['_leaf'] = ['dir' => '>', 'order' => $node->node_order];
        }

        $this->getLeaves($query, $options);
        $query->order(["{$this->closureTable}Children.node_order" => 'ASC']);
    }

    /**
     * Get all leaves nested under same parent as given node.
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the node for which leaves will be found.
     *
     * @param Query $query
     * @param array $options
     */
    protected function getSiblingLeaves(Query $query, array $options)
    {
        $node = $this->__table->find()
            ->select(['parent_id', 'node_order' , 'id', 'child_id'])
            ->where(['child_id' => $options['for'], 'depth' => 1])
            ->first();

        $options['for'] = -1; //FIXME

        if (!empty($node)) {
            $options['for'] = $node->parent_id;
        }

        $this->getLeaves($query, $options);
        $query->order(["{$this->closureTable}Children.node_order" => 'ASC']);
    }

    public function findLeaf(Query $query, array $options)
    {
        if (empty($options['for'])) {
            throw new InvalidArgumentException("The 'for' key is required for find('leaf').");
        }

        switch ($options['get']) {
            case 'first':
                $this->getFirstLeaf($query, $options);
                break;
            case 'last':
                $this->getLastLeaf($query, $options);
                break;
            case 'prev':
                $this->getPrevLeaf($query, $options);
                break;
            case 'next':
                $this->getNextLeaf($query, $options);
                break;
            default:
                throw new InvalidArgumentException("Invalid 'get' key for find('leaf').");
        }

        return $query;
    }

    /**
     * Get leaf with lowest order for a given node.
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the parent node for which leaf will be found.
     *
     * @param Query $query
     * @param array $options
     */
    protected function getFirstLeaf(Query $query, array $options)
    {
        $this->getLeaves($query, $options);
        $query->order(["{$this->closureTable}Children.node_order" => 'ASC'])->limit(1);
    }

    /**
     * Get leaf with highest order for a given node.
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the parent node for which leaf will be found.
     *
     * @param Query $query
     * @param array $options
     */
    protected function getLastLeaf(Query $query, array $options)
    {
        $this->getLeaves($query, $options);
        $query->order(["{$this->closureTable}Children.node_order" => 'DESC'])->limit(1);
    }

    /**
     * Get first leaf having order lower then given node.
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the node for which leaf will be found.
     *
     * @param Query $query
     * @param array $options
     */
    protected function getPrevLeaf(Query $query, array $options)
    {
        $this->getPrevLeaves($query, $options);
        $query->limit(1);
    }

    /**
     * Get first leaf having order higher then given node.
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the node for which which leaf will be found.
     *
     * @param Query $query
     * @param array $options
     */
    protected function getNextLeaf(Query $query, array $options)
    {
        $this->getNextLeaves($query, $options);
        $query->limit(1);
    }

    /**
     * Get all siblings of given node.
     *
     * ### Available options are:
     *
     * - for: Integer, the id of node for which siblings will be found.
     *
     * @param Query $query
     * @param array $options
     * @return Query
     * @throws InvalidArgumentException When the 'for' key is not passed in $options
     */
    public function findSiblings(Query $query, array $options)
    {
        if (empty($options['for'])) {
            throw new InvalidArgumentException("The 'for' key is required for find('siblings').");
        }

        if (array_key_exists('get', $options)) {
            switch ($options['get']) {
                case 'prev':
                    $this->getPrevSiblings($query, $options);
                    break;
                case 'next':
                    $this->getNextSiblings($query, $options);
                    break;
                default:
                    throw new InvalidArgumentException("Invalid 'get' key for find('siblings').");
            }
        } else {
            $this->getSiblings($query, $options);
            $query->order(['node_order' => 'ASC']);
        }

        return $query;
    }

    /**
     * Returns query to find all siblings on the left (having lower node order) for given node.
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the node for which previous siblings will be get.
     *
     * @param Query $query
     * @param array $options
     */
    protected function getPrevSiblings(Query $query, array $options)
    {
        $options['direction'] = '<';
        $this->getSiblings($query, $options);

        $query->order(['node_order' => 'ASC']);
    }

    /**
     * Returns query to find all siblings on the right (having higher node order) for given node.
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the node for which next siblings will be get..
     *
     * @param Query $query
     * @param array $options
     */
    protected function getNextSiblings(Query $query, array $options)
    {
        $options['direction'] = '>';
        $this->getSiblings($query, $options);

        $query->order(['node_order' => 'ASC']);
    }

    public function findSibling(Query $query, array $options)
    {
        if (empty($options['for'])) {
            throw new InvalidArgumentException("The 'for' key is required for find('sibling').");
        }

        switch ($options['get']) {
            case 'first':
                $this->getFirstSibling($query, $options);
                break;
            case 'last':
                $this->getLastSibling($query, $options);
                break;
            case 'prev':
                $this->getPrevSibling($query, $options);
                break;
            case 'next':
                $this->getNextSibling($query, $options);
                break;
            default:
                throw new InvalidArgumentException("Invalid 'get' key for find('sibling').");
        }

        return $query;
    }

    /**
     * Returns query to find youngest sibling (having lowest node order) of given node.
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the node for which youngest sibling will be get.
     *
     * @param Query $query
     * @param array $options
     */
    protected function getFirstSibling(Query $query, array $options)
    {
        $this->getSiblings($query, $options);
        $query->order(['node_order' => 'ASC'])->limit(1);
    }

    /**
     * Returns query to find oldest sibling (having highest node order) of given node.
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the node for which youngest oldest will be get.
     *
     * @param Query $query
     * @param array $options
     */
    protected function getLastSibling(Query $query, array $options)
    {
        $this->getSiblings($query, $options);
        $query->order(['node_order' => 'DESC'])->limit(1);
    }

    /**
     * Returns query to find first sibling on the left (having lower node order) for given node.
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the node for which previous sibling will be get.
     *
     * @param Query $query
     * @param array $options
     */
    protected function getPrevSibling(Query $query, array $options)
    {
        $this->getPrevSiblings($query, $options);
        $query->limit(1);
    }

    /**
     * Returns query to find first siblings on the right (having higher node order) for given node.
     *
     * ### Available options are:
     *
     * - for: Integer, the id of the node for which next sibling will be get.
     *
     * @param Query $query
     * @param array $options
     */
    protected function getNextSibling(Query $query, array $options)
    {
        $this->getNextSiblings($query, $options);
        $query->limit(1);
    }

    public function has(EntityInterface $node, $what, $type = null)
    {
        switch ($what) {
            case 'branches':
                $cnt = $this->_hasBranches($node, $type);
                break;
            case 'leaves':
                $cnt = $this->_hasLeaves($node, $type);
                break;
            case 'siblings':
                $cnt = $this->_hasSiblings($node, $type);
                break;
            case 'children':
                $cnt = $this->countChildren($node);
                break;
            default:
                throw new InvalidArgumentException("Invalid node type for count().");
        }
        return $cnt;
    }

    /**
     * Checks existence of branches for given node.
     *
     * @param EntityInterface $node The entity to checks branches for.
     * @return int Number of branches.
     */
    private function _hasBranches(EntityInterface $node, $type = null)
    {
        switch ($type) {
            case 'prev':
                $cnt = $this->hasPrevBranches($node);
                break;
            case 'next':
                $cnt = $this->hasNextBranches($node);
                break;
            default:
                $cnt = $this->hasBranches($node);
        }

        return $cnt;
    }

    /**
     * Check if the node has any branches.
     *
     * @param EntityInterface $node The entity to perform check for.
     * @return bool Returns true if node has branches or false otherwise.
     */
    protected function hasBranches(EntityInterface $node)
    {
        return (bool) $this->countBranches($node);
    }

    /**
     * Check if the node has any siblings, having lower node order, which are branches.
     *
     * @param EntityInterface $node The entity to perform check for.
     * @return bool
     */
    protected function hasPrevBranches(EntityInterface $node)
    {
        return (bool) $this->countPrevBranches($node);
    }

    /**
     * Check if the node has any siblings, having higher node order, which are branches.
     *
     * @param EntityInterface $node
     * @return bool
     */
    protected function hasNextBranches(EntityInterface $node)
    {
        return (bool) $this->countNextBranches($node);
    }

    /**
     * Checks existence of branches for given node.
     *
     * @param EntityInterface $node The entity to checks branches for.
     * @return int Number of branches.
     */
    private function _hasLeaves(EntityInterface $node, $type = null)
    {
        switch ($type) {
            case 'prev':
                $cnt = $this->hasPrevLeaves($node);
                break;
            case 'next':
                $cnt = $this->hasNextLeaves($node);
                break;
            case 'siblings':
                $cnt = $this->hasSiblingLeaves($node);
                break;
            default:
                $cnt = $this->hasLeaves($node);
        }

        return $cnt;
    }

    /**
     * Check if the node has any siblings, having lower node order, which are leaves.
     *
     * @param EntityInterface $node The entity to perform check for.
     * @return bool
     */
    protected function hasPrevLeaves(EntityInterface $node)
    {
        return (bool) $this->countPrevLeaves($node);
    }

    /**
     * Check if the node has any siblings, having higher node order, which are leaves.
     *
     * @param EntityInterface $node
     * @return bool
     */
    protected function hasNextLeaves(EntityInterface $node)
    {
        return (bool) $this->countNextLeaves($node);
    }

    /**
     * Check if the node has any leaves.
     *
     * @param EntityInterface $node The entity to perform check for.
     * @return bool Returns true if node has leaves or false otherwise.
     */
    protected function hasLeaves(EntityInterface $node)
    {
        return (bool) $this->countLeaves($node);
    }

    /**
     * Check if the node has any leaves.
     *
     * @param EntityInterface $node The entity to perform check for.
     * @return bool Returns true if node has leaves or false otherwise.
     */
    protected function hasSiblingLeaves(EntityInterface $node)
    {
        return (bool) $this->countSiblingLeaves($node);
    }

    /**
     * Checks existence of siblings for given node.
     *
     * @param EntityInterface $node The entity to checks branches for.
     * @return int Number of branches.
     */
    private function _hasSiblings(EntityInterface $node, $type = null)
    {
        switch ($type) {
            case 'prev':
                $cnt = $this->hasPrevSiblings($node);
                break;
            case 'next':
                $cnt = $this->hasNextSiblings($node);
                break;
            default:
                $cnt = $this->hasSiblings($node);
        }

        return $cnt;
    }

    /**
     * Checks if the node has any siblings.
     *
     *
     * @param EntityInterface $node The entity to perform check for.
     * @return bool Returns true if node has siblings or false otherwise.
     */
    protected function hasSiblings(EntityInterface $node)
    {
        return (bool) $this->countSiblings($node);
    }

    /**
     * Checks if the node has any siblings with node order lower then the node.
     *
     * @param EntityInterface $node The entity to perform check for.
     * @return bool
     */
    protected function hasPrevSiblings(EntityInterface $node)
    {
        return (bool) $this->countPrevSiblings($node);
    }

    /**
     * Checks if the node has any siblings with node order higher then the node.
     *
     * @param EntityInterface $node
     * @return bool
     */
    protected function hasNextSiblings(EntityInterface $node)
    {
        return (bool) $this->countNextSiblings($node);
    }

    /**
     * Checks if the node has a parent.
     *
     * @param EntityInterface $node The entity to perform check for.
     * @return bool Returns true if node has parent or false otherwise.
     */
    public function hasParent(EntityInterface $node)
    {
        return (bool) $this->getDepth($node);
    }

    /**
     * Checks if the node has any children.
     *
     * @param EntityInterface $node The entity to perform check for.
     * @return bool Returns true if node has children or false otherwise.
     */
    public function hasChildren(EntityInterface $node)
    {
        return (bool) $this->countChildren($node);
    }

    /**
     * Get the node depth.
     *
     * @param EntityInterface $node The entity to get depth for.
     * @return int Node depth.
     */
    public function getDepth(EntityInterface $node)
    {
        return $this->__table->find()
                ->select(['depth'])
                ->where([
                    'child_id' => $node->get($this->_table->getPrimaryKey()),
                    'parent_id IS' => null
                ])->extract('depth')
                ->first() - 1;
    }

    /**
     * Get the node order.
     *
     * @param EntityInterface $node The entity to get order for.
     * @return int Node order
     */
    public function getOrder(EntityInterface $node)
    {
        return $this->__table->find()
            ->select(['node_order'])
            ->where([
                'child_id' => $node->get($this->_table->getPrimaryKey()),
                'depth' => 1
            ])->extract('node_order')
            ->first();
    }

    public function count(EntityInterface $node, $what, $type = null)
    {
        switch ($what) {
            case 'branches':
                $cnt = $this->_countBranches($node, $type);
                break;
            case 'leaves':
                $cnt = $this->_countLeaves($node, $type);
                break;
            case 'siblings':
                $cnt = $this->_countSiblings($node, $type);
                break;
            case 'children':
                $cnt = $this->countChildren($node);
                break;
            default:
                throw new InvalidArgumentException("Invalid node type for count().");
        }

        return $cnt;
    }

    /**
     * Count children nodes.
     *
     * @param EntityInterface $node The entity to count children for.
     * @return int Number of children nodes.
     */
    public function countChildren(EntityInterface $node)
    {
        return $this->__table->find()->where([
            'parent_id' => $node->get($this->_table->getPrimaryKey()),
            'depth' => 1
        ])->count();
    }

    /**
     * Count branches for given node.
     *
     * @param EntityInterface $node The entity to count branches for.
     * @return int Number of branches.
     */
    private function _countBranches(EntityInterface $node, $type = null)
    {
        switch ($type) {
            case 'prev':
                $cnt = $this->countPrevBranches($node);
                break;
            case 'next':
                $cnt = $this->countNextBranches($node);
                break;
            default:
                $cnt = $this->countBranches($node);
        }

        return $cnt;
    }

    /**
     * Count branches for given node.
     *
     * @param EntityInterface $node The entity to count branches for.
     * @return int Number of branches.
     */
    protected function countBranches(EntityInterface $node)
    {
        return $this->_table->find('branches', [
            'for' => $node->get($this->_table->getPrimaryKey())
        ])->count();
    }

    /**
     * Count branches having order lower then given node.
     *
     * @param EntityInterface $node The entity to count branches for.
     * @return int Number of branches.
     */
    protected function countPrevBranches(EntityInterface $node)
    {
        return $this->_table->find('branches', [
            'for' => $node->get($this->_table->getPrimaryKey()),
            'get' => 'prev'
        ])->count();
    }

    /**
     * Count branches having order higher then given node.
     *
     * @param EntityInterface $node The entity to count branches for.
     * @return int Number of branches.
     */
    protected function countNextBranches(EntityInterface $node)
    {
        return $this->_table->find('branches', [
            'for' => $node->get($this->_table->getPrimaryKey()),
            'get' => 'next'
        ])->count();
    }

    /**
     * Count leaves for given node.
     *
     * @param EntityInterface $node The entity to count leaves for.
     * @return int Number of leaves.
     */
    private function _countLeaves(EntityInterface $node, $type = null)
    {
        switch ($type) {
            case 'prev':
                $cnt = $this->countPrevLeaves($node);
                break;
            case 'next':
                $cnt = $this->countNextLeaves($node);
                break;
            case 'siblings':
                $cnt = $this->countSiblingLeaves($node);
                break;
            default:
                $cnt = $this->countLeaves($node);
        }
        return $cnt;
    }

    /**
     * Count leaves having order lower then given node.
     *
     * @param EntityInterface $node The entity to count leaves for.
     * @return int Number of leaves.
     */
    protected function countPrevLeaves(EntityInterface $node)
    {
        return $this->_table->find('leaves', [
            'for' => $node->get($this->_table->getPrimaryKey()),
            'get' => 'prev'
        ])->count();
    }

    /**
     * Count leaves having order higher then given node.
     *
     * @param EntityInterface $node The entity to count leaves for.
     * @return int Number of leaves.
     */
    protected function countNextLeaves(EntityInterface $node)
    {
        return $this->_table->find('leaves', [
            'for' => $node->get($this->_table->getPrimaryKey()),
            'get' => 'next'
        ])->count();
    }

    /**
     * Count leaves for given node.
     *
     * @param EntityInterface $node The entity to count leaves for.
     * @return int Number of leaves.
     */
    protected function countLeaves(EntityInterface $node)
    {
        return $this->_table->find('leaves', [
            'for' => $node->get($this->_table->getPrimaryKey())
        ])->count();
    }

    /**
     * Count leaves nested under same parent as given node.
     *
     * @param EntityInterface $node The entity to count leaves for.
     * @return int Number of leaves.
     */
    protected function countSiblingLeaves(EntityInterface $node)
    {
        return $this->_table->find('leaves', [
            'for' => $node->get($this->_table->getPrimaryKey()),
            'get' => 'siblings'
        ])->andWhere([
            "{$this->closureTable}Children.child_id IS NOT" => $node->id
        ])->count();
    }

    /**
     * Count siblings for given node.
     *
     * @param EntityInterface $node The entity to count siblings for.
     * @return int Number of siblings.
     */
    private function _countSiblings(EntityInterface $node, $type = null)
    {
        switch ($type) {
            case 'prev':
                $cnt = $this->countPrevSiblings($node);
                break;
            case 'next':
                $cnt = $this->countNextSiblings($node);
                break;
            default:
                $cnt = $this->countSiblings($node);
        }
        return $cnt;
    }

    protected function countSiblings(EntityInterface $node)
    {
        return $this->_table->find('siblings', [
            'for' => $node->get($this->_table->getPrimaryKey())
        ])->count();
    }

    /**
     * Count siblings having order lower then given node.
     *
     * @param EntityInterface $node The entity to count siblings for.
     * @return int Number of siblings.
     */
    protected function countPrevSiblings(EntityInterface $node)
    {
        return $this->_table->find('siblings', [
            'for' => $node->get($this->_table->getPrimaryKey()),
            'get' => 'prev'
        ])->count();
    }

    /**
     * Count siblings having order higher then given node.
     *
     * @param EntityInterface $node The entity to count siblings for.
     * @return int Number of siblings.
     */
    protected function countNextSiblings(EntityInterface $node)
    {
        return $this->_table->find('siblings', [
            'for' => $node->get($this->_table->getPrimaryKey()),
            'get' => 'next'
        ])->count();
    }

    /**
     * Build query to find children.
     *
     * @param Query $query
     * @param array $options
     */
    protected function getChildren(Query $query, array $options)
    {
        $query->innerJoinWith("{$this->closureTable}Children", function ($q) use ($options) {
            $conditions = ["{$this->closureTable}Children.parent_id" => $options['for']];
            return $q->where($conditions)->innerJoinWith("{$this->closureTable}ChildrenInOrder", function ($q) {
                return $q->innerJoinWith("{$this->closureTable}Parents", function ($q) {
                    return $q->select([
                        '_treePath' => $q->func()->group_concat([
                            $q->func()->lpad(['parent_id', 10, 0])
                        ])
                    ]);
                });
            });
        });

        if (!isset($options['withSelf']) || !$options['withSelf']) {
            $query->andWhere([$this->_table->getAlias() . '.' . $this->_table->getPrimaryKey() . ' IS NOT' => $options['for']]);
        }

        $query->group(["{$this->closureTable}ChildrenInOrder.child_id"]);
        $query->order([
            '_treePath' => 'ASC',
            "{$this->closureTable}ChildrenInOrder.node_order" => 'ASC'
        ]);

        $query->formatResults(function ($results) {
            return $results->map(function ($row) {
                unset($row['_treePath']);
                return $row;
            });
        });
    }

    /**
     * Build query to find branches.
     *
     * @param Query $query
     * @param array $options
     */
    protected function getBranches(Query $query, array $options)
    {
        $query->innerJoinWith("{$this->closureTable}Children", function ($q) use ($options) {
            $conditions = ['depth' => 1];

            if ($options['for'] === null) {
                $conditions["{$this->closureTable}Children.parent_id IS"] = $options['for'];
            } else {
                $conditions["{$this->closureTable}Children.parent_id"] = $options['for'];
            }

            if (isset($options['_branch'])) {
                $conditions["{$this->closureTable}Children.node_order {$options['_branch']['dir']}"] = $options['_branch']['order'];
            }

            return $q->where($conditions)->innerJoinWith("{$this->closureTable}GrandChildren", function ($q) {
                return $q->where([
                    "{$this->closureTable}GrandChildren.depth" => 1
                ]);
            });
        })->group(["{$this->closureTable}Children.id"]);
    }

    /**
     * Build query to find leaves.
     *
     * @param Query $query
     * @param array $options
     */
    protected function getLeaves(Query $query, array $options)
    {
        $query->innerJoinWith("{$this->closureTable}Children", function ($q) use ($options) {
            $conditions = ['depth' => 1];

            if ($options['for'] === null) {
                $conditions["{$this->closureTable}Children.parent_id IS"] = $options['for'];
            } else {
                $conditions["{$this->closureTable}Children.parent_id"] = $options['for'];
            }

            if (isset($options['_leaf'])) {
                $conditions["{$this->closureTable}Children.node_order {$options['_leaf']['dir']}"] = $options['_leaf']['order'];
            }

            return $q->where($conditions)->notMatching("{$this->closureTable}GrandChildren", function ($q) {
                return $q->where([
                    "{$this->closureTable}GrandChildren.depth" => 1
                ]);
            });
        });
    }

    /**
     * Build query to find siblings.
     *
     * @param Query $query
     * @param array $options
     */
    protected function getSiblings(Query $query, array $options)
    {
        $parent = $this->__table->find()
            ->select(['parent_id', 'node_order'])
            ->where([
                'child_id' => $options['for'],
                'depth' => 1,
                'node_order IS NOT' => null,
            ])->first();

        $query->innerJoinWith("{$this->closureTable}Children", function ($q) use ($options, $parent) {
            $parentConditions = $parent->parent_id !== null
                ? ["{$this->closureTable}Children.parent_id" => $parent->parent_id]
                : ["{$this->closureTable}Children.parent_id IS" => $parent->parent_id];

            $conditions = Hash::merge($parentConditions, [
                "{$this->closureTable}Children.depth" => 1,
            ]);

            if (isset($options['direction'])) {
                $conditions["{$this->closureTable}Children.node_order {$options['direction']}"] = $parent->node_order;
            }

            return $q->where($conditions);
        });

        if (!isset($options['withSelf']) || !$options['withSelf']) {
            $query->andWhere([$this->_table->getAlias() . '.' . $this->_table->getPrimaryKey() . ' IS NOT' => $options['for']]);
        }
    }

    /**
     * Performs bulk insert of data into closure table.
     *
     * @param $data
     */
    private function insertData($data)
    {
        $query = $this->__table->query();
        $columns = ['parent_id', 'child_id', 'depth', 'node_order'];
        $values = new ValuesExpression($columns, $query->typeMap()->types([]));
        $query->insert($columns);

        foreach (array_chunk($data, 1000) as $chunk) {
            $values->setValues($chunk);
            $query->values($values);
            $query->execute();
        }
    }

    /**
     * Nests sub tree under new parent in a given tree.
     *
     * @param array $parentsTree List of new parents.
     * @param array $childrenToNest List of children to be nested in parents array.
     * @param int $newParentId Id of parent from $parentsTree under which $childrenToNest will be nested.
     */
    private function nest(&$parentsTree, $childrenToNest, $newParentId)
    {
        foreach ($parentsTree as &$branch) {
            if ($branch[$this->_table->getPrimaryKey()] === $newParentId) {
                $childrenToNest[0] = $this->_table->patchEntity($childrenToNest[0], [$this->config['parentField'] => $newParentId]);
                $branch['children'] = Hash::merge($branch['children'], $childrenToNest);
            } elseif (!empty($branch['children'])) {
                $this->nest($branch['children'], $childrenToNest, $newParentId);
            }
        }
    }

    /**
     * Build array of children with list of theirs ancestors.
     *
     * ### format of returned array:
     * [
     *      p_id => [
     *          c_id => [
     *              'child_id' => '',
     *              'ancestors' => [ 0 => id, 1 => id]
     *          ]
     *      ]
     * ]
     *
     * - p_id - Parent node id;
     * - c_id - Child node id;
     * - child_id - Child node id (same as c_id);
     * - ancestors - Array of ids of parent nodes;
     *
     * @param array $threaded Cake's find('threaded') converted to array.
     * @param array $hierarchy Output array.
     * @param int $startFrom Id of node from which hierarchy will be build.
     * @param array $ancestors Ancestors stack.
     */
    private function buildHierarchy(array $threaded, &$hierarchy, $startFrom, $ancestors = [])
    {
        foreach ($threaded as $child) {
            if (!empty($child['parent_id']) && !in_array($child['parent_id'], $ancestors)) {
                $ancestors[] = $child['parent_id'];
            }

            if ($child['parent_id'] >= $startFrom) {
                $hierarchy[$child['parent_id']][$child['id']] = [
                    'child_id' => $child['id'],
                    'ancestors' => $ancestors
                ];
            }
            if (!empty($child['children'])) {
                $this->buildHierarchy($child['children'], $hierarchy, $startFrom, $ancestors);
            }
        }
    }

    /**
     * Builds array of insert data.
     *
     * @param array $hierarchy Array of children with list of theirs ancestors.
     * @return array
     */
    private function buildInsertData(array $hierarchy)
    {
        $rows = [];
        // Prepare dara for bulk inserts
        foreach ($hierarchy as $children) {
            $nodeOrder = 1;
            foreach ($children as $child) {
                // Set correct order for root node.
                $order = count($child['ancestors']) === 0 ? 1 : null;
                $rows[] = [
                    'parent_id' => null,
                    'child_id' => $child['child_id'],
                    'depth' => count($child['ancestors']) + 1,
                    'node_order' => $order
                ];
                foreach (array_values($child['ancestors']) as $idx => $parentId) {
                    $depth = count($child['ancestors']) - $idx;
                    $order = $depth === 1 ? $nodeOrder : null;
                    $rows[] = [
                        'parent_id' => $parentId,
                        'child_id' => $child['child_id'],
                        'depth' => $depth,
                        'node_order' => $order
                    ];
                }
                $nodeOrder++;

                $rows[] = [
                    'parent_id' => $child['child_id'],
                    'child_id' => $child['child_id'],
                    'depth' => 0,
                    'node_order' => null
                ];
            }
        }

        return $rows;
    }

    /**
     * Moves node to desired destination and updates node order of preceding or following nodes.
     *
     * ### Available destination options are:
     *
     * - 'up': Moves node up by one position
     * - 'down': Moves node up by one position
     * - 'top': Moves node to first position
     * - 'bottom': Moves node to last position
     * - ['to' => $nodeOrder]: Move node to given position
     * - ['after' => $nodeId]: Moves node after given $nodeId
     * - ['before' => $nodeId]: Moves node before given $nodeId
     *
     * @param EntityInterface $node Note to be move
     * @param mixed $destination
     */
    public function move(EntityInterface $node, $destination)
    {
        switch ($destination) {
            case 'up':
                $this->_moveUp($node);
                break;
            case 'down':
                $this->_moveDown($node);
                break;
            case 'top':
                $this->_moveTop($node);
                break;
            case 'bottom':
                $this->_moveBottom($node);
                break;
            case is_array($destination) && isset($destination['to']):
                $this->_moveTo($node, $destination['to']);
                break;
            case is_array($destination) && isset($destination['before']):
                $this->_moveBefore($node, $destination['before']);
                break;
            case is_array($destination) && isset($destination['after']):
                $this->_moveAfter($node, $destination['after']);
                break;
            default:
                throw new InvalidArgumentException("Invalid 'get' key for find('leaf').");
        }
    }

    /**
     * Moves given node up by one position, moves preceding node down by one position.
     *
     * @param EntityInterface $node
     */
    private function _moveUp(EntityInterface $node)
    {
        //FIXME select both nodes in one query ?
        $sibling = $this->__table->find()->where([
            'child_id' => $node->get($this->_table->getPrimaryKey()),
            'depth' => 1,
            'node_order >' => 1
        ])->first();

        if (!empty($sibling)) {

            $conditions = $sibling->parent_id !== null
                ? ["parent_id"  => $sibling->parent_id]
                : ["parent_id IS"  => $sibling->parent_id];

            $prevSibling = $this->__table->find()->where(Hash::merge($conditions, [
                'parent_id' => $sibling->parent_id,
                'node_order' => $sibling->node_order - 1,
                'depth' => 1
            ]))->first();

            if (!empty($prevSibling)) {
                $this->__table->patchEntity($prevSibling, ['node_order' => $prevSibling->node_order + 1]);
                $this->__table->patchEntity($sibling, ['node_order' => $sibling->node_order - 1]);
                $this->__table->saveMany([$prevSibling, $sibling]);
            }
        }
    }

    /**
     * Moves given node down by one position, moves following node up by one position.
     *
     * @param EntityInterface $node
     */
    private function _moveDown(EntityInterface $node)
    {
        //FIXME select both nodes in one query ?
        $sibling = $this->__table->find()->where([
            'child_id' => $node->get($this->_table->getPrimaryKey()),
            'depth' => 1,
            'node_order <' => $this->count($node, 'siblings') + 1
        ])->first();

        if (!empty($sibling)) {
            $conditions = $sibling->parent_id !== null
                ? ["parent_id"  => $sibling->parent_id]
                : ["parent_id IS"  => $sibling->parent_id];

            $nextSibling = $this->__table->find()->where(Hash::merge($conditions, [
                'node_order' => $sibling->node_order + 1,
                'depth' => 1
            ]))->first();

            if (!empty($nextSibling)) {
                $this->__table->patchEntity($nextSibling, ['node_order' => $nextSibling->node_order - 1]);
                $this->__table->patchEntity($sibling, ['node_order' => $sibling->node_order + 1]);
                $this->__table->saveMany([$nextSibling, $sibling]);
            }
        }
    }

    /**
     * Moves given note to first position, moves all nodes preceding the node down by one position.
     *
     * @param EntityInterface $node
     */
    private function _moveTop(EntityInterface $node)
    {
        $sibling = $this->__table->find()->where([
            'child_id' => $node->get($this->_table->getPrimaryKey()),
            'depth' => 1,
            'node_order >' => 1
        ])->first();

        if (!empty($sibling)) {
            $this->moveNodesDown($sibling->parent_id, $sibling->node_order);

            $this->__table->patchEntity($sibling, ['node_order' => 1]);
            $this->__table->save($sibling);
        }
    }

    /**
     * Moves given node to last position, moves all nodes following the node down by one position.
     *
     * @param EntityInterface $node
     */
    private function _moveBottom(EntityInterface $node)
    {
        $sibling = $this->__table->find()->where([
            'child_id' => $node->get($this->_table->getPrimaryKey()),
            'depth' => 1,
            'node_order <' => $this->count($node, 'siblings') + 1
        ])->first();

        if (!empty($sibling)) {
            $this->moveNodesUp($sibling->parent_id, $sibling->node_order);

            $this->__table->patchEntity($sibling, ['node_order' =>   $this->count($node, 'siblings') + 1]);
            $this->__table->save($sibling);
        }
    }

    private function _moveTo(EntityInterface $node, $newOrder)
    {
        $sibling = $this->__table->find()->where([
            'child_id' => $node->get($this->_table->getPrimaryKey()),
            'depth' => 1,
            'node_order <>' => $newOrder,
        ])->first();

        if (!empty($sibling)) {
            if ($sibling->node_order > $newOrder) { // moving down
                $this->moveNodesDown($sibling->parent_id, $sibling->node_order, $newOrder);
            } else { // moving up
                $this->moveNodesUp($sibling->parent_id, $sibling->node_order, $newOrder);
            }

            $this->__table->patchEntity($sibling, ['node_order' => $newOrder]);
            $this->__table->save($sibling);
        }
    }

    private function _moveBefore(EntityInterface$node, $beforeNode)
    {
        if ($node->get($this->_table->getPrimaryKey()) === $beforeNode) {
            return false;
        }

        $sibling = $this->__table->find()->where([
            'child_id' => $node->get($this->_table->getPrimaryKey()),
            'depth' => 1,
        ])->first();

        $nextSibling = $this->__table->find()->where([
            'child_id' => $beforeNode,
            'depth' => 1,
        ])->first();

        if (!empty($sibling) && !empty($nextSibling) && $sibling->node_order !== $nextSibling->node_order -1) {
            if ($sibling->node_order > $nextSibling->node_order) {
                $this->moveNodesDown($sibling->parent_id, $sibling->node_order, $nextSibling->node_order);
                $this->__table->patchEntity($sibling, ['node_order' => $nextSibling->node_order]);
            } elseif ($sibling->node_order < $nextSibling->node_order) {
                $this->moveNodesUp($sibling->parent_id, $sibling->node_order, $nextSibling->node_order -1);
                $this->__table->patchEntity($sibling, ['node_order' => $nextSibling->node_order - 1]);
            }
            return $this->__table->save($sibling);
        }

        return false;
    }

    private function _moveAfter(EntityInterface $node, $beforeNode)
    {
        if ($node->get($this->_table->getPrimaryKey()) === $beforeNode) {
            return false;
        }

        $sibling = $this->__table->find()->where([
            'child_id' => $node->get($this->_table->getPrimaryKey()),
            'depth' => 1,
        ])->first();

        $nextSibling = $this->__table->find()->where([
            'child_id' => $beforeNode,
            'depth' => 1,
        ])->first();

        if (!empty($sibling) && !empty($nextSibling) && $sibling->node_order !== $nextSibling->node_order + 1) {
            if ($sibling->node_order > $nextSibling->node_order) {
                $this->moveNodesDown($sibling->parent_id, $sibling->node_order, $nextSibling->node_order);
                $this->__table->patchEntity($sibling, ['node_order' => $nextSibling->node_order ]);
            } elseif ($sibling->node_order < $nextSibling->node_order) {
                $this->moveNodesUp($sibling->parent_id, $sibling->node_order, $nextSibling->node_order);
                $this->__table->patchEntity($sibling, ['node_order' => $nextSibling->node_order]);
            }
            return  $this->__table->save($sibling);
        }

        return false;
    }

    private function moveNodesUp($parentId, $from, $max = null)
    {
        $query = $this->__table->query();
        $exp = $query->newExpr();

        $movement = clone $exp;
        $movement->add('node_order')->add("1")->setConjunction('-');

        $where = clone $exp;

        $parentConditions = $parentId !== null
            ? ['parent_id' => $parentId]
            : ['parent_id IS' => $parentId];

        $conditions = Hash::merge($parentConditions, [
            'node_order >' => $from,
            'depth' =>  1
        ]);

        if ($max !== null) {
            $conditions['node_order <='] = $max;
        }

        $where->add($conditions)->setConjunction('AND');

        $query->update()
            ->set($exp->eq('node_order', $movement))
            ->where($where);

        $query->execute()->closeCursor();
    }

    private function moveNodesDown($parentId, $from, $max = null)
    {
        $query = $this->__table->query();
        $exp = $query->newExpr();

        $movement = clone $exp;
        $movement->add('node_order')->add("1")->setConjunction('+');

        $where = clone $exp;

        $parentConditions = $parentId !== null
            ? ['parent_id' => $parentId]
            : ['parent_id IS' => $parentId];

        $conditions = Hash::merge($parentConditions, [
            'node_order <' => $from,
            'depth' =>  1
        ]);

        if ($max !== null) {
            $conditions['node_order >='] = $max;
        }

        $where->add($conditions)->setConjunction('AND');

        $query->update()
            ->set($exp->eq('node_order', $movement))
            ->where($where);

        $query->execute()->closeCursor();
    }

    private function createAssociations($table = null)
    {
        $table = $table === null ? $this->_table : $table;

        if ($this->closureTable !== null) {
            $this->__table = TableRegistry::get($this->closureTable);

            $table->hasOne($this->closureTable, [
                'className' => $this->closureTable,
                'foreignKey' => 'parent_id',
                'joinType' => 'INNER'
            ]);

            // Association used to search hierarchy up
            $table->hasOne("{$this->closureTable}Parents", [
                'className' => $this->closureTable,
                'foreignKey' => 'parent_id',
                'joinType' => 'INNER'
            ]);

            // Association used to search hierarchy down
            $table->hasOne("{$this->closureTable}Children", [
                'className' => $this->closureTable,
                'foreignKey' => 'child_id',
                'joinType' => 'INNER'
            ]);

            // Association used to determine if sub node is a leaf or a branch
            $table->{"{$this->closureTable}Children"}->hasOne("{$this->closureTable}GrandChildren", [
                'className' => $this->closureTable,
                'foreignKey' => false,
                'conditions' => "{$this->closureTable}GrandChildren.parent_id = {$this->closureTable}Children.child_id",
                'joinType' => 'INNER'
            ]);

            // Association used to order children by node_order
            $this->_table->{"{$this->closureTable}Children"}->hasOne("{$this->closureTable}ChildrenInOrder", [
                'className' => $this->closureTable,
                'foreignKey' => false,
                'conditions' => [
                    "{$this->closureTable}ChildrenInOrder.child_id = {$this->closureTable}Children.child_id",
                    "{$this->closureTable}ChildrenInOrder.node_order IS NOT" => null
                ],
                'joinType' => 'INNER'
            ]);

            // Association used to list children under parents.
            $this->_table->{"{$this->closureTable}Children"}->{"{$this->closureTable}ChildrenInOrder"}->hasMany("{$this->closureTable}Parents", [
                'className' => $this->closureTable,
                'foreignKey' => false,
                'conditions' => [
                    "{$this->closureTable}ChildrenInOrder.child_id = {$this->closureTable}Parents.child_id",
                ],
                'joinType' => 'INNER'
            ]);
        }
    }
}
