<?php

namespace App\Test\TestCase\Model\Behavior;

use Cake\TestSuite\TestCase;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use InvalidArgumentException;
use Cake\ORM\Query;


/**
 * App\Model\Behavior\ClosureTableBehavior Test Case
 */
class ClosureTableBehaviorTest extends TestCase
{

    public $fixtures = [
        'app.categories',
        'app.categories_closure_table',
    ];

    /**
     * Test subject
     *
     * @var \App\Model\Behavior\ClosureTableBehavior
     */
    public $ClosureTableBehavior;

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->table = TableRegistry::get('Categories');

        if (!$this->table->hasBehavior('ClosureTable')) {
            $this->table->addBehavior('ClosureTable', [
                'closure' => [
                    'className' => 'CategoriesClosure',
                ],
                'table' => [
                    'parent_id' => 'parent_id'
                ]
            ]);
        }

        $this->insertData();
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->table);
        parent::tearDown();
    }

    private function insertData()
    {
        $records = [
            [
                'id' => 1,
                'parent_id' => null,
                'name' => 'Root 1'
            ],
            [
                'id' => 2,
                'parent_id' => 1,
                'name' => 'branch 1'
            ],
            [
                'id' => 3,
                'parent_id' => 2,
                'name' => 'Branch 1-2'
            ],
            [
                'id' => 4,
                'parent_id' => 1,
                'name' => 'Leaf 1-4'
            ],
            [
                'id' => 5,
                'parent_id' => 2,
                'name' => 'Leaf 1-2'
            ],
            [
                'id' => 6,
                'parent_id' => 2,
                'name' => 'Leaf 1-2'
            ],
            [
                'id' => 7,
                'parent_id' => 3,
                'name' => 'Leaf 1-2-3'
            ],
            [
                'id' => 8,
                'parent_id' => 3,
                'name' => 'Branch 1-2-3'
            ],
            [
                'id' => 9,
                'parent_id' => 8,
                'name' => 'Branch 1-2-3-8'
            ],
            [
                'id' => 10,
                'parent_id' => 9,
                'name' => 'Branch 1-2-3-8-9'
            ],
            [
                'id' => 11,
                'parent_id' => 6,
                'name' => 'Leaf 1-2-6'
            ],
            [
                'id' => 12,
                'parent_id' => 3,
                'name' => 'Leaf 1-2-3'
            ],
            [
                'id' => 13,
                'parent_id' => 3,
                'name' => 'Leaf 1-2-3'
            ],
            [
                'id' => 14,
                'parent_id' => 8,
                'name' => 'Branch 1-2-3-8'
            ],
            [
                'id' => 15,
                'parent_id' => 14,
                'name' => 'Leaf 1-2-3-8-14'
            ],
            [
                'id' => 16,
                'parent_id' => 8,
                'name' => 'Branch 1-2-3-8'
            ],
            [
                'id' => 17,
                'parent_id' => 16,
                'name' => 'Branch 1-2-3-8-16'
            ],
            [
                'id' => 18,
                'parent_id' => null,
                'name' => 'Leaf'
            ],
        ];

        $entities = $this->table->newEntities($records);
        $this->table->saveMany($entities);
    }

    public function testFindParents()
    {
        try {
            $this->table->find('parents');
            $this->fail('Failed to throw InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e);
        }

        // FIXME Change to act same as find children
        $expected = $this->table->find()->where(['id IN' => [1, 2, 5]])->find('threaded')->enableHydration(false)->toArray();
        $query = $this->table->find('parents', ['for' => 5, 'withSelf' => true]);

        $this->assertInstanceOf(Query::class, $query);

        $result = $query->find('threaded')->enableHydration(false)->toArray();

        $this->assertEquals($expected, $result);

        $query = $this->table->find('parents', ['for' => 5, 'withSelf' => false]);
        $result = $query->find('threaded')->enableHydration(false)->toArray();

        $expected[0]['children'][0]['children'] = [];
        $this->assertEquals($expected, $result);
    }

    public function testFindChildren()
    {
        try {
            $this->table->find('children');
            $this->fail('Failed to throw InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e);
        }

        $query = $this->table->find('children', ['for' => 1, 'withSelf' => true]);

        $this->assertInstanceOf(Query::class, $query);
        $result = $query->extract('id')->toArray();

        $expected = [1, 2, 4, 3, 5, 6, 7, 11, 8, 12, 13, 9, 14, 16, 10, 15, 17];
        $this->assertEquals($expected, $result);


        $query = $this->table->find('children', ['for' => 1, 'withSelf' => false]);
        $result = $query->extract('id')->toArray();

        unset($expected[0]);
        $expected = array_values($expected);

        $this->assertSame($expected, $result);
    }

    public function testFindFirstChild()
    {
        try {
            $this->table->find('child');
            $this->fail('Failed to throw InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e);
        }

        $query = $this->table->find('child', ['for' => 2, 'get' => 'first']);
        $this->assertInstanceOf(Query::class, $query);
        $this->assertSame(3, $query->extract('id')->first());
    }

    public function testFindLastChild()
    {
        try {
            $this->table->find('child');
            $this->fail('Failed to throw InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e);
        }

        $query = $this->table->find('child', ['for' => 2, 'get' => 'last']);
        $this->assertInstanceOf(Query::class, $query);
        $this->assertSame(6, $query->extract('id')->first());
    }

    public function testFindChildAt()
    {
        try {
            $this->table->find('child', ['for' => 1, 'node_order' => null]);
            $this->fail('Failed to throw InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e);
        }
        $query = $this->table->find('child', ['for' => 2, 'node_order' => 2]);

        $this->assertInstanceOf(Query::class, $query);
        $this->assertSame(5, $query->extract('id')->first());
    }

    public function testFindChildrenRange()
    {
        try {
            $this->table->find('children');
            $this->fail('Failed to throw InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e);
        }

        try {
            $this->table->find('children', ['for' => 1, 'node_order' => null]);
            $this->fail('Failed to throw InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e);
        }

        $query = $this->table->find('children', ['for' => 2, 'node_order' => ['from' => 2, 'to' => 3]]);
        $this->assertInstanceOf(Query::class, $query);
        $this->assertSame([5, 6], $query->extract('id')->toArray());
        $this->assertSame([3, 5, 6], $this->table->find('children', ['for' => 2, 'node_order' => ['from' => 1]])->extract('id')->toArray());
        $this->assertSame([3, 5], $this->table->find('children', ['for' => 2, 'node_order' => ['to' => 2]])->extract('id')->toArray());
        $this->assertEmpty($this->table->find('children', ['for' => 10, 'node_order' => ['to' => 2]])->extract('id')->toArray());
    }


    public function testFindBranches()
    {
        try {
            $this->table->find('branches');
            $this->fail('Failed to throw InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e);
        }

        $query = $this->table->find('branches', ['for' => 1]);

        $this->assertInstanceOf(Query::class, $query);
        $this->assertSame([2], $query->extract('id')->toArray());
        $this->assertSame([3, 6], $this->table->find('branches', ['for' => 2])->extract('id')->toArray());
    }

    public function testFindPrevBranches()
    {
        try {
            $this->table->find('branches', ['for' => 1, 'get' => null]);
            $this->fail('Failed to throw InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e);
        }

        $query = $this->table->find('branches', ['for' => 4, 'get' => 'prev']);

        $this->assertInstanceOf(Query::class, $query);
        $this->assertSame([2], $query->extract('id')->toArray());
        $this->assertSame([14, 9], $this->table->find('branches', ['for' => 16, 'get' => 'prev'])->extract('id')->toArray());
        $this->assertEmpty($this->table->find('branches', ['for' => 2, 'get' => 'prev'])->extract('id')->toArray());
        $this->assertEmpty($this->table->find('branches', ['for' => 99, 'get' => 'prev'])->extract('id')->toArray());
    }

    public function testFindNextBranches()
    {
        $query = $this->table->find('branches', ['for' => 5, 'get' => 'next']);

        $this->assertInstanceOf(Query::class, $query);
        $this->assertSame([6], $query->extract('id')->toArray());
        $this->assertSame([14, 16], $this->table->find('branches', ['for' => 9, 'get' => 'next'])->extract('id')->toArray());
        $this->assertEmpty($this->table->find('branches', ['for' => 11, 'get' => 'next'])->extract('id')->toArray());
        $this->assertEmpty($this->table->find('branches', ['for' => 99, 'get' => 'next'])->extract('id')->toArray());
    }

    public function testFindSiblingBranches()
    {
        $this->assertSame([], $this->table->find('branches', ['for' => 1, 'get' => 'siblings'])->extract('id')->toArray());
        $this->assertSame([2], $this->table->find('branches', ['for' => 4, 'get' => 'siblings'])->extract('id')->toArray());
        $this->assertSame([8], $this->table->find('branches', ['for' => 12, 'get' => 'siblings'])->extract('id')->toArray());
        $this->assertSame([], $this->table->find('branches', ['for' => 17, 'get' => 'siblings'])->extract('id')->toArray());
    }

    public function testFindFirstBranch()
    {
        try {
            $this->table->find('branch');
            $this->fail('Failed to throw InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e);
        }

        $query = $this->table->find('branch', ['for' => 1, 'get' => 'first']);

        $this->assertInstanceOf(Query::class, $query);
        $this->assertSame(2, $query->extract('id')->first());
        $this->assertSame(3, $this->table->find('branch', ['for' => 2, 'get' => 'first'])->extract('id')->first());
        $this->assertNull($this->table->find('branch', ['for' => 12, 'get' => 'first'])->extract('id')->first());
        $this->assertNull($this->table->find('branch', ['for' => 99, 'get' => 'first'])->extract('id')->first());
    }

    public function testFindLastBranch()
    {
        $query = $this->table->find('branch', ['for' => 1, 'get' => 'last']);

        $this->assertInstanceOf(Query::class, $query);
        $this->assertSame(2, $query->extract('id')->first());
        $this->assertSame(6, $this->table->find('branch', ['for' => 2, 'get' => 'last'])->extract('id')->first());
        $this->assertNull($this->table->find('branch', ['for' => 99, 'get' => 'last'])->extract('id')->first());
    }

    public function testFindPrevBranch()
    {
        $query = $this->table->find('branch', ['for' => 4, 'get' => 'prev']);

        $this->assertInstanceOf(Query::class, $query);
        $this->assertSame(2, $query->extract('id')->first());
        $this->assertSame(8, $this->table->find('branch', ['for' => 12, 'get' => 'prev'])->extract('id')->first());
        $this->assertNull($this->table->find('branch', ['for' => 2, 'get' => 'prev'])->extract('id')->first());
        $this->assertNull($this->table->find('branch', ['for' => 99, 'get' => 'prev'])->extract('id')->first());
    }

    public function testFindNextBranch()
    {

        $query = $this->table->find('branch', ['for' => 3, 'get' => 'next']);

        $this->assertInstanceOf(Query::class, $query);
        $this->assertSame(6, $query->extract('id')->first());
        $this->assertSame(8, $this->table->find('branch', ['for' => 7, 'get' => 'next'])->extract('id')->first());
        $this->assertNull($this->table->find('branch', ['for' => 11, 'get' => 'next'])->extract('id')->first());
        $this->assertNull($this->table->find('branch', ['for' => 99, 'get' => 'next'])->extract('id')->first());
    }

    public function testFindLeaves()
    {
        try {
            $this->table->find('leaves');
            $this->fail('Failed to throw InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e);
        }

        $query = $this->table->find('leaves', ['for' => 1]);
        $this->assertInstanceOf(Query::class, $query);
        $this->assertSame([4], $query->extract('id')->toArray());
        $this->assertSame([7, 12, 13], $this->table->find('leaves', ['for' => 3])->extract('id')->toArray());
        $this->assertEmpty($this->table->find('leaves', ['for' => 99])->extract('id')->toArray());
    }

    public function testFindPrevLeaves()
    {
        $query = $this->table->find('leaves', ['for' => 7, 'get' => 'prev']);
        $this->assertInstanceOf(Query::class, $query);
        $this->assertNull($query->extract('id')->first());
        $this->assertSame([12, 7], $this->table->find('leaves', ['for' => 13, 'get' => 'prev'])->extract('id')->toArray());
        $this->assertEmpty($this->table->find('leaves', ['for' => 99, 'get' => 'prev'])->extract('id')->toArray());
    }

    public function testFindNextLeaves()
    {
        $query = $this->table->find('leaves', ['for' => 12, 'get' => 'next']);
        $this->assertInstanceOf(Query::class, $query);
        $this->assertSame([12, 13], $this->table->find('leaves', ['for' => 7, 'get' => 'next'])->extract('id')->toArray());
        $this->assertEmpty($this->table->find('leaves', ['for' => 99, 'get' => 'next'])->extract('id')->toArray());
    }

    public function testFindFirstLeaf()
    {
        try {
            $this->table->find('leaf');
            $this->fail('Failed to throw InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e);
        }

        $query = $this->table->find('leaf', ['for' => 1, 'get' => 'first']);
        $this->assertInstanceOf(Query::class, $query);
        $this->assertSame(4, $query->extract('id')->first());
        $this->assertSame(7, $this->table->find('leaf', ['for' => 3, 'get' => 'first'])->extract('id')->first());
        $this->assertNull($this->table->find('leaf', ['for' => 99, 'get' => 'first'])->extract('id')->first());
    }

    public function testFindLastLeaf()
    {
        $query = $this->table->find('leaf', ['for' => 1, 'get' => 'last']);
        $this->assertInstanceOf(Query::class, $query);
        $this->assertSame(4, $query->extract('id')->first());
        $this->assertSame(13, $this->table->find('leaf', ['for' => 3, 'get' => 'last'])->extract('id')->first());
        $this->assertNull($this->table->find('leaf', ['for' => 99, 'get' => 'last'])->extract('id')->first());
    }

    public function testFindPrevLeaf()
    {
        $query = $this->table->find('leaf', ['for' => 7, 'get' => 'prev']);
        $this->assertInstanceOf(Query::class, $query);
        $this->assertNull($query->extract('id')->first());
        $this->assertSame(12, $this->table->find('leaf', ['for' => 13, 'get' => 'prev'])->extract('id')->first());
        $this->assertNull($this->table->find('leaf', ['for' => 99, 'get' => 'prev'])->extract('id')->first());
    }

    public function testFindNextLeaf()
    {
        $query = $this->table->find('leaf', ['for' => 12, 'get' => 'next']);
        $this->assertInstanceOf(Query::class, $query);
        $this->assertSame(13, $query->extract('id')->first());
        $this->assertSame(5, $this->table->find('leaf', ['for' => 3, 'get' => 'next'])->extract('id')->first());
        $this->assertNull($this->table->find('leaf', ['for' => 99, 'get' => 'next'])->extract('id')->first());
    }

    public function testFindSiblings()
    {
        try {
            $this->table->find('siblings');
            $this->fail('Failed to throw InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e);
        }

        $query = $this->table->find('siblings', ['for' => 5]);
        $this->assertInstanceOf(Query::class, $query);
        $this->assertEquals([3, 6], $query->extract('id')->toArray());
        $this->assertEmpty($this->table->find('siblings', ['for' => 10])->extract('id')->toArray());
    }

    public function testPrevSiblings()
    {
        $query = $this->table->find('siblings', ['for' => 6, 'get' => 'prev']);
        $this->assertInstanceOf(Query::class, $query);
        $this->assertSame([3, 5], $query->extract('id')->toArray());
        $this->assertSame([3], $this->table->find('siblings', ['for' => 5, 'get' => 'prev'])->extract('id')->toArray());
        $this->assertEmpty($this->table->find('siblings', ['for' => 3, 'get' => 'prev'])->extract('id')->toArray());
    }

    public function testNextSiblings()
    {
        $query = $this->table->find('siblings', ['for' => 3, 'get' => 'next']);
        $this->assertInstanceOf(Query::class, $query);
        $this->assertSame([5, 6], $query->extract('id')->toArray());
        $this->assertSame([6], $this->table->find('siblings', ['for' => 5, 'get' => 'next'])->extract('id')->toArray());
        $this->assertEmpty($this->table->find('siblings', ['for' => 10, 'get' => 'next'])->extract('id')->toArray());
    }

    public function testFirstSibling()
    {
        try {
            $this->table->find('sibling', ['get' => 'first']);
            $this->fail('Failed to throw InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e);
        }

        $query = $this->table->find('sibling', ['for' => 6, 'get' => 'first']);

        $this->assertInstanceOf(Query::class, $query);
        $this->assertSame(3, $query->extract('id')->first());

        $this->assertSame(7, $this->table->find('sibling', ['for' => 12, 'get' => 'first'])->extract('id')->first());
        $this->assertEmpty($this->table->find('sibling', ['for' => 11, 'get' => 'first'])->extract('id')->first());
    }

    public function testLastSibling()
    {
        $query = $this->table->find('sibling', ['for' => 6, 'get' => 'last']);

        $this->assertInstanceOf(Query::class, $query);
        $this->assertSame(5, $query->extract('id')->first());

        $this->assertSame(13, $this->table->find('sibling', ['for' => 12, 'get' => 'last'])->extract('id')->first());
        $this->assertEmpty($this->table->find('sibling', ['for' => 11, 'get' => 'last'])->extract('id')->first());
    }

    public function testPrevSibling()
    {

        $query = $this->table->find('sibling', ['for' => 5, 'get' => 'prev']);
        $this->assertInstanceOf(Query::class, $query);
        $this->assertSame(3, $query->extract('id')->first());
        $this->assertEmpty($this->table->find('sibling', ['for' => 10, 'get' => 'prev'])->extract('id')->first());
    }

    public function testNextSibling()
    {
        $query = $this->table->find('sibling', ['for' => 5, 'get' => 'next']);
        $this->assertInstanceOf(Query::class, $query);
        $this->assertSame(6, $query->extract('id')->first());
        $this->assertEmpty($this->table->find('sibling', ['for' => 10, 'get' => 'next'])->extract('id')->first());
    }

    public function testGetDepth()
    {
        $this->assertSame(0, $this->table->getDepth($this->table->get(1)));
        $this->assertSame(1, $this->table->getDepth($this->table->get(2)));
        $this->assertSame(5, $this->table->getDepth($this->table->get(10)));
    }

    public function testGetOrder()
    {
        $this->assertSame(3, $this->table->getOrder($this->table->get(6)));
    }

    public function testMoveUp()
    {
        $node = $this->table->get(1);
        $this->table->move($node, 'up');
        $this->assertSame(1, $this->table->getOrder($this->table->get(1)));
        $this->assertSame(2, $this->table->getOrder($this->table->get(4)));

        $node = $this->table->get(8);
        $this->table->move($node, 'up');
        $this->assertSame(1, $this->table->getOrder($this->table->get(8)));
        $this->assertSame(2, $this->table->getOrder($this->table->get(7)));
        $this->assertSame(3, $this->table->getOrder($this->table->get(12)));
        $this->assertSame(4, $this->table->getOrder($this->table->get(13)));

        $node = $this->table->get(7);
        $this->table->move($node, 'up');
        $this->assertSame(1, $this->table->getOrder($this->table->get(7)));
        $this->assertSame(2, $this->table->getOrder($this->table->get(8)));
        $this->assertSame(3, $this->table->getOrder($this->table->get(12)));
        $this->assertSame(4, $this->table->getOrder($this->table->get(13)));

        $node = $this->table->get(13);
        $this->table->move($node, 'up');
        $this->assertSame(1, $this->table->getOrder($this->table->get(7)));
        $this->assertSame(2, $this->table->getOrder($this->table->get(8)));
        $this->assertSame(3, $this->table->getOrder($this->table->get(13)));
        $this->assertSame(4, $this->table->getOrder($this->table->get(12)));
    }

    public function testMoveDown()
    {
        $node = $this->table->get(1);
        $this->table->move($node, 'down');
        $this->assertSame(1, $this->table->getOrder($this->table->get(18)));
        $this->assertSame(2, $this->table->getOrder($this->table->get(1)));

        $node = $this->table->get(7);
        $this->table->move($node, 'down');
        $this->assertSame(1, $this->table->getOrder($this->table->get(8)));
        $this->assertSame(2, $this->table->getOrder($this->table->get(7)));
        $this->assertSame(3, $this->table->getOrder($this->table->get(12)));
        $this->assertSame(4, $this->table->getOrder($this->table->get(13)));

        $node = $this->table->get(8);
        $this->table->move($node, 'down');
        $this->assertSame(1, $this->table->getOrder($this->table->get(7)));
        $this->assertSame(2, $this->table->getOrder($this->table->get(8)));
        $this->assertSame(3, $this->table->getOrder($this->table->get(12)));
        $this->assertSame(4, $this->table->getOrder($this->table->get(13)));

        $node = $this->table->get(13);
        $this->table->move($node, 'down');
        $this->assertSame(3, $this->table->getOrder($this->table->get(12)));
        $this->assertSame(4, $this->table->getOrder($this->table->get(13)));
    }

    public function testMoveTop()
    {
        $node = $this->table->get(1);
        $this->table->move($node, 'top');

        $this->assertSame(1, $this->table->getOrder($this->table->get(1)));
        $this->assertSame(2, $this->table->getOrder($this->table->get(18)));

        $node = $this->table->get(8);
        $this->table->move($node, 'top');
        $this->assertSame(1, $this->table->getOrder($this->table->get(8)));
        $this->assertSame(2, $this->table->getOrder($this->table->get(7)));
        $this->assertSame(3, $this->table->getOrder($this->table->get(12)));
        $this->assertSame(4, $this->table->getOrder($this->table->get(13)));

        $node = $this->table->get(7);
        $this->table->move($node, 'top');
        $this->assertSame(1, $this->table->getOrder($this->table->get(7)));
        $this->assertSame(2, $this->table->getOrder($this->table->get(8)));

        $node = $this->table->get(13);
        $this->table->move($node, 'top');
        $this->assertSame(1, $this->table->getOrder($this->table->get(13)));
        $this->assertSame(2, $this->table->getOrder($this->table->get(7)));
        $this->assertSame(3, $this->table->getOrder($this->table->get(8)));
        $this->assertSame(4, $this->table->getOrder($this->table->get(12)));
    }

    public function testMoveBottom()
    {
        $node = $this->table->get(1);
        $this->table->move($node, 'bottom');
        $this->assertSame(1, $this->table->getOrder($this->table->get(18)));
        $this->assertSame(2, $this->table->getOrder($this->table->get(1)));

        $node = $this->table->get(8);
        $this->table->move($node, 'bottom');
        $this->assertSame(1, $this->table->getOrder($this->table->get(7)));
        $this->assertSame(2, $this->table->getOrder($this->table->get(12)));
        $this->assertSame(3, $this->table->getOrder($this->table->get(13)));
        $this->assertSame(4, $this->table->getOrder($this->table->get(8)));

        $node = $this->table->get(7);
        $this->table->move($node, 'bottom');
        $this->assertSame(1, $this->table->getOrder($this->table->get(12)));
        $this->assertSame(2, $this->table->getOrder($this->table->get(13)));
        $this->assertSame(3, $this->table->getOrder($this->table->get(8)));
        $this->assertSame(4, $this->table->getOrder($this->table->get(7)));

        $node = $this->table->get(7);
        $this->table->move($node, 'bottom');
        $this->assertSame(1, $this->table->getOrder($this->table->get(12)));
        $this->assertSame(2, $this->table->getOrder($this->table->get(13)));
        $this->assertSame(3, $this->table->getOrder($this->table->get(8)));
        $this->assertSame(4, $this->table->getOrder($this->table->get(7)));
    }

    public function testMoveTo()
    {
        $node = $this->table->get(1);
        $this->table->move($node, ['to' => 2]);
        $this->assertSame(1, $this->table->getOrder($this->table->get(18)));
        $this->assertSame(2, $this->table->getOrder($this->table->get(1)));

        $node = $this->table->get(8);
        $this->table->move($node, ['to' => 4]);
        $this->assertSame(1, $this->table->getOrder($this->table->get(7)));
        $this->assertSame(2, $this->table->getOrder($this->table->get(12)));
        $this->assertSame(3, $this->table->getOrder($this->table->get(13)));
        $this->assertSame(4, $this->table->getOrder($this->table->get(8)));

        $node = $this->table->get(7);
        $this->table->move($node, ['to' => 4]);
        $this->assertSame(1, $this->table->getOrder($this->table->get(12)));
        $this->assertSame(2, $this->table->getOrder($this->table->get(13)));
        $this->assertSame(3, $this->table->getOrder($this->table->get(8)));
        $this->assertSame(4, $this->table->getOrder($this->table->get(7)));

        $node = $this->table->get(7);
        $this->table->move($node, ['to' => 4]);
        $this->assertSame(1, $this->table->getOrder($this->table->get(12)));
        $this->assertSame(2, $this->table->getOrder($this->table->get(13)));
        $this->assertSame(3, $this->table->getOrder($this->table->get(8)));
        $this->assertSame(4, $this->table->getOrder($this->table->get(7)));
    }

    public function testMoveBefore()
    {
        $node = $this->table->get(1);
        $this->table->move($node, ['before' => 18]);

        $this->assertSame(1, $this->table->getOrder($this->table->get(1)));
        $this->assertSame(2, $this->table->getOrder($this->table->get(18)));

        $node = $this->table->get(18);
        $this->table->move($node, ['before' => 1]);
        $this->assertSame(1, $this->table->getOrder($this->table->get(18)));
        $this->assertSame(2, $this->table->getOrder($this->table->get(1)));

        $node = $this->table->get(8);
        $this->table->move($node, ['before' => 13]);
        $this->assertSame(1, $this->table->getOrder($this->table->get(7)));
        $this->assertSame(2, $this->table->getOrder($this->table->get(12)));
        $this->assertSame(3, $this->table->getOrder($this->table->get(8)));
        $this->assertSame(4, $this->table->getOrder($this->table->get(13)));

        $node = $this->table->get(7);
        $this->table->move($node, ['before' => 13]);
        $this->assertSame(1, $this->table->getOrder($this->table->get(12)));
        $this->assertSame(2, $this->table->getOrder($this->table->get(8)));
        $this->assertSame(3, $this->table->getOrder($this->table->get(7)));
        $this->assertSame(4, $this->table->getOrder($this->table->get(13)));

        $node = $this->table->get(7);
        $this->table->move($node, ['before' => 13]);
        $this->assertSame(1, $this->table->getOrder($this->table->get(12)));
        $this->assertSame(2, $this->table->getOrder($this->table->get(8)));
        $this->assertSame(3, $this->table->getOrder($this->table->get(7)));
        $this->assertSame(4, $this->table->getOrder($this->table->get(13)));
    }

    public function testMoveAfter()
    {
        $node = $this->table->get(1);
        $this->table->move($node, ['after' => 18]);
        $this->assertSame(1, $this->table->getOrder($this->table->get(18)));
        $this->assertSame(2, $this->table->getOrder($this->table->get(1)));

        $node = $this->table->get(18);
        $this->table->move($node, ['after' => 1]);
        $this->assertSame(1, $this->table->getOrder($this->table->get(1)));
        $this->assertSame(2, $this->table->getOrder($this->table->get(18)));

        $node = $this->table->get(8);
        $this->table->move($node, ['after' => 12]);
        $this->assertSame(1, $this->table->getOrder($this->table->get(7)));
        $this->assertSame(2, $this->table->getOrder($this->table->get(12)));
        $this->assertSame(3, $this->table->getOrder($this->table->get(8)));
        $this->assertSame(4, $this->table->getOrder($this->table->get(13)));

        $node = $this->table->get(7);
        $this->table->move($node, ['after' => 8]);
        $this->assertSame(1, $this->table->getOrder($this->table->get(12)));
        $this->assertSame(2, $this->table->getOrder($this->table->get(8)));
        $this->assertSame(3, $this->table->getOrder($this->table->get(7)));
        $this->assertSame(4, $this->table->getOrder($this->table->get(13)));

        $node = $this->table->get(7);
        $this->table->move($node, ['after' => 8]);
        $this->assertSame(2, $this->table->getOrder($this->table->get(8)));
        $this->assertSame(3, $this->table->getOrder($this->table->get(7)));
    }

    public function testCountSiblings()
    {
        $this->assertSame(2, $this->table->count($this->table->get(3), 'siblings'));
        $this->assertSame(3, $this->table->count($this->table->get(7), 'siblings'));
        $this->assertSame(0, $this->table->count($this->table->get(11), 'siblings'));
    }

    public function testCountPrevSiblings()
    {
        $this->assertSame(0, $this->table->count($this->table->get(3), 'siblings', 'prev'));
        $this->assertSame(1, $this->table->count($this->table->get(5), 'siblings', 'prev'));
        $this->assertSame(2, $this->table->count($this->table->get(6), 'siblings', 'prev'));
        $this->assertSame(0, $this->table->count($this->table->get(11), 'siblings', 'prev'));
    }

    public function testCountNextSiblings()
    {
        $this->assertSame(2, $this->table->count($this->table->get(3), 'siblings', 'next'));
        $this->assertSame(1, $this->table->count($this->table->get(5), 'siblings', 'next'));
        $this->assertSame(0, $this->table->count($this->table->get(6), 'siblings', 'next'));
        $this->assertSame(0, $this->table->count($this->table->get(11), 'siblings', 'next'));
    }

    public function testCountChildren()
    {
        $this->assertSame(2, $this->table->count($this->table->get(1), 'children'));
        $this->assertSame(3, $this->table->count($this->table->get(2), 'children'));
        $this->assertSame(0, $this->table->count($this->table->get(11), 'children'));
    }

    public function testCountBranches()
    {
        $this->assertSame(1, $this->table->count($this->table->get(1), 'branches'));
        $this->assertSame(1, $this->table->count($this->table->get(3), 'branches'));
        $this->assertSame(3, $this->table->count($this->table->get(8), 'branches'));
    }

    public function testCountPrevBranches()
    {
        $this->assertSame(1, $this->table->count($this->table->get(4), 'branches', 'prev'));
        $this->assertSame(2, $this->table->count($this->table->get(16), 'branches', 'prev'));
    }

    public function testCountNextBranches()
    {
        $this->assertSame(0, $this->table->count($this->table->get(11), 'branches', 'next'));
        $this->assertSame(1, $this->table->count($this->table->get(5), 'branches', 'next'));
        $this->assertSame(2, $this->table->count($this->table->get(9), 'branches', 'next'));

    }

    public function testCountLeaves()
    {
        $this->assertSame(1, $this->table->count($this->table->get(1), 'leaves'));
        $this->assertSame(3, $this->table->count($this->table->get(3), 'leaves'));
        $this->assertSame(0, $this->table->count($this->table->get(5), 'leaves'));
    }

    public function testCountPrevLeaves()
    {
        $this->assertSame(0, $this->table->count($this->table->get(1), 'leaves', 'prev'));
        $this->assertSame(1, $this->table->count($this->table->get(12), 'leaves', 'prev'));
        $this->assertSame(2, $this->table->count($this->table->get(13), 'leaves', 'prev'));
    }

    public function testCountNextLeaves()
    {
        $this->assertSame(0, $this->table->count($this->table->get(1), 'leaves', 'next'));
        $this->assertSame(2, $this->table->count($this->table->get(7), 'leaves', 'next'));
        $this->assertSame(1, $this->table->count($this->table->get(12), 'leaves', 'next'));
    }

    public function testCountSiblingLeaves()
    {
        $this->assertSame(1, $this->table->count($this->table->get(1), 'leaves', 'siblings'));
        $this->assertSame(2, $this->table->count($this->table->get(7), 'leaves', 'siblings'));
        $this->assertSame(2, $this->table->count($this->table->get(12), 'leaves', 'siblings'));
    }

    public function testHasParent()
    {
        $this->assertFalse($this->table->hasParent($this->table->get(1)));
        $this->assertTrue($this->table->hasParent($this->table->get(11)));
    }

    public function testHasChildren()
    {
        $this->assertTrue($this->table->hasChildren($this->table->get(1)));
        $this->assertFalse($this->table->hasChildren($this->table->get(11)));
    }

    public function testHasBranches()
    {
        $this->assertTrue($this->table->has($this->table->get(1), 'branches'));
        $this->assertFalse($this->table->has($this->table->get(11), 'branches'));
    }

    public function testHasPrevBranches()
    {
        $this->assertFalse($this->table->has($this->table->get(1), 'branches', 'prev'));
        $this->assertFalse($this->table->has($this->table->get(7), 'branches', 'prev'));

        $this->assertTrue($this->table->has($this->table->get(12), 'branches', 'prev'));
        $this->assertTrue($this->table->has($this->table->get(18), 'branches', 'prev'));
    }

    public function testHasNextBranches()
    {
        $this->assertFalse($this->table->has($this->table->get(1), 'branches', 'next'));
        $this->assertFalse($this->table->has($this->table->get(13), 'branches', 'next'));

        $this->assertTrue($this->table->has($this->table->get(7), 'branches', 'next'));
        $this->assertTrue($this->table->has($this->table->get(9), 'branches', 'next'));
    }

    public function testHasLeaves()
    {
        $this->assertTrue($this->table->has($this->table->get(1), 'leaves'));
        $this->assertTrue($this->table->has($this->table->get(6), 'leaves'));
    }

    public function testHasPrevLeaves()
    {
        $this->assertFalse($this->table->has($this->table->get(7), 'leaves', 'prev'));
        $this->assertTrue($this->table->has($this->table->get(8), 'leaves', 'prev'));
    }

    public function testHasNextLeaves()
    {
        $this->assertFalse($this->table->has($this->table->get(13), 'leaves', 'next'));
        $this->assertTrue($this->table->has($this->table->get(8), 'leaves', 'next'));
    }

    public function testSiblingLeaves()
    {
        $this->assertTrue($this->table->has($this->table->get(1), 'leaves', 'siblings'));
        $this->assertTrue($this->table->has($this->table->get(3), 'leaves', 'siblings'));
        $this->assertFalse($this->table->has($this->table->get(4), 'leaves', 'siblings'));
        $this->assertFalse($this->table->has($this->table->get(17), 'leaves', 'siblings'));
    }


    public function testHasSiblings()
    {
        $this->assertTrue($this->table->has($this->table->get(1), 'siblings'));
        $this->assertTrue($this->table->has($this->table->get(4), 'siblings'));
        $this->assertFalse($this->table->has($this->table->get(17), 'siblings'));
    }

    public function testHasPrevSiblings()
    {
        $this->assertFalse($this->table->has($this->table->get(1), 'siblings', 'prev'));
        $this->assertTrue($this->table->has($this->table->get(4), 'siblings', 'prev'));
    }

    public function testHasNextSiblings()
    {
        $this->assertTrue($this->table->has($this->table->get(1), 'siblings', 'next'));
        $this->assertFalse($this->table->has($this->table->get(4), 'siblings', 'next'));
    }
}
