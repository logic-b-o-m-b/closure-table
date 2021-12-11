# ClosureTabe behavior for cakePHP (early alpha)

Behavior was tested with CakePHP 3.6

ClosureTable behavior is perfect for maitaining multiple trees of any size stored in same table.

It works well with small (built of few nodes) and large (built of hudreads of thousands nodes) trees.

Behavior can be used in both - multi user and multi tenant applications.

Key features of behavior:
- Tree can be altered in easy way;
- Tree can be queried in many ways;
- Tree nodes can be ordered and re-ordered;
- Size of tree and tree structure may have minimal impact on reading/wrting performance;
- Concurrent updates of tree may have minimal impact on reading/wrting performance;
- Compatibility with native Tree behavior.

Behavior uses Closure Table Model to store, retrieve and manage hiererchical data.

## Closure Table Model

Closure Table Model requires additional table to store map of the tree.

Structure of such table
<table width=100%>
<tr>
  <th>Field</th>
  <th>type</th>
  <th>Constraints</th>
  <th>Description</th>
</tr>
<tr>  
  <td valign="top">parent_id</td>
  <td>int(10); nullable</td>
  <td valign="top">FK (TABLE_NAME.PRIMARY_KEY) </td>  
   <td valign="top">Null when parent_id is a very top of the tree</td>  
</tr>
<tr>  
  <td valign="top">child_id</td>
  <td>int(10);</td>
  <td valign="top">FK (TABLE_NAME.PRIMARY_KEY) </td>
  <td></td>  
</tr>
<tr>  
  <td valign="top">depth</td>
  <td valign="top">int(10);</td>
  <td></td>  
  <td>Distance between parent_id and child_id</td>  
</tr>  
<tr>  
  <td valign="top">node_order</td>
  <td valign="top">int(10); nullable</td>
  <td></td>
  <td>Used to order nodes nested under same parent. <br />Field will only have value when depth = 1</td>  
</tr> 
</table>

For every node in the tree (child_id) table stores path from the node to the top of the tree.

Path starts form node itself (parent_id = child_id), then goes up trough every ancestor (parent_id) and ends at the very top of the tree (parent_id = null). 

For every node that belongs to the path a new entry is saved in the table. Entry contains ids of both nodes (parent_id, child_id), distance between them (depth) and node order (node_order). 

Initial value of depth is 0, and is incremented by 1 with every change of parent_id. 

Default value for node_order is null. Field has a numeric value only when the ancestor (parent_id) is immediate parent of the node (child_id) and depth = 1.
Initial numeric value is 1 and is incremented by 1 for every following node nested under same parent.

For every path values of depth and node_order are re-set to initial / default values.

Data stored in closure table for 4 nodes nested like below will be as follows: <br />

    
    (1)
     ├── (2)
     └── (3)
          └── (4)
    
<a name="back"></a>
<table>
  <tr>
    <th>parent_id</th>
    <th>child_id</th>
    <th>depth</th>
    <th>node_order</th>
  </tr>
  
  <tr>
    <td></td>
    <td>1</td>
    <td>1</td>
    <td>1</td>
  </tr>  

  <tr>
    <td>1</td>
    <td>1</td>
    <td>0</td>
    <td></td>
  </tr>    
  
  <tr>
    <td></td>
    <td>2</td>
    <td>2</td>
    <td></td>
  </tr>      
  
  <tr>
    <td>1</td>
    <td>2</td>
    <td>1</td>
    <td>1</td>
  </tr>    
  <tr>
    <td>2</td>
    <td>2</td>
    <td>0</td>
    <td></td>
  </tr>    
  
   <tr>
    <td></td>
    <td>3</td>
    <td>2</td>
    <td></td>
  </tr>      
  
  <tr>
    <td>1</td>
    <td>3</td>
    <td>1</td>
    <td>2</td>
  </tr>    
  
  <tr>
    <td>3</td>
    <td>3</td>
    <td>0</td>
    <td></td>
  </tr>    
   <tr>
    <td></td>
    <td>4</td>
    <td>3</td>
    <td></td>
  </tr>      
  
  <tr>
    <td>1</td>
    <td>4</td>
    <td>2</td>
    <td></td>
  </tr>    
  <tr>
    <td>3</td>
    <td>4</td>
    <td>1</td>
    <td>1</td>
  </tr>     
  <tr>
    <td>4</td>
    <td>4</td>
    <td>0</td>
    <td></td>
  </tr>    
  
</table>

[Step by step explanations for above table](https://github.com/logic-b-o-m-b/closure-table/blob/main/building_tree_paths.md)

Depending on tree structure and number of nodes the table can become very long. Size and structure of the tree may have a minimal impact on writing and reading performance.

Addiditional reading: [Moving Subtrees in Closure Table Hierarchies - PERCONA](https://www.percona.com/blog/2011/02/14/moving-subtrees-in-closure-table/)

## Requirements

Additional table (closure table) is required for every table using behavior.

## Installation
Composer package will be available from beta version.
Copy over test and behavior manually to root directory. This tests require two models included in src/Model (it's still early alpha)

## Setup
For every table that uses ClosureTable there are 2 setup steps required: **Migration**, and **Behavior Configuration** . 
When ClosureTable replaces Tree beahavior additional step is reuired: **Data Import** 

### Migration
Closure table is required for every table using behavior.

To make setup cleaner use following naming convetion for closure tables <TABLE_NAME>_closure; if table is named 'categories' then closure table should be named 'categories_closure'.

[Example migration](https://github.com/logic-b-o-m-b/closure-table/blob/main/CreateCategoriesClosure.example_migration.php) can be used as template for closure table migrations.

Migration should be run before proceeding to next step.


### Behavior Configuration
Behavior has to be added to table.

When following naming convetions (and field that stores id of ancestor is named parent_id):

```php
class Categories extends Table
{ 
    public function initialize(array $config)
    {
        $this->addBehavior('ClosureTable');
    }
}
```
When not follwoing naming convetions:

```php
class Categories extends Table
{ 
    public function initialize(array $config)
    {
        $this->addBehavior('ClosureTable', [
            'tableName' => 'CategoriesHierarchy',
            'parentField' => 'parent_cat_id'
        ]);
    }
}
```

Tree behavior, if table uses it, has to be removed.

### Data Import (optional)
This step is required only if behavior was added to a table that already contains data.

Importing tree data can be done in one of three ways.

**Importing using included shell**

This way allows to run import without writing, deploying and running migrations.


Syntax: 
```sh
bin/cake ClosureTable import TableName ParentField
```
Import of the tree (when following naming conventions)
```sh
bin/cake ClosureTable import Categories
```

Import of the tree  (when not following naming conventions)
```sh
bin/cake ClosureTable import CategoriesHierarchy parent_cat_id
```

**Importing with migration**

This way allows to run tree import along with other migrations.

```php
use Cake\ORM\TableRegistry;
use Migrations\AbstractMigration;

class ImportCategoriesClosure extends AbstractMigration
{
   
    public function change()
    {    
        $table = TableRegistry::get('Categories');
        $table->importTree();
    }
}    
```
**Importing from controller**

This way is adviced only when testing/debuging.

```php
namespace App\Controller;

use Cake\ORM\TableRegistry;

class CategoriesController extends AppController
{
    public function importTree()
    {
        $this->Categories->import();        
    }
}
```

## Working with trees

### Writing tree information.

#### Creating trees, multi trees, inserting, deleting and moving nodes
```php
$table = TableRegistry::get('Categories');

// Creating a new tree
$vegetables = $table->newEntity(['parent_id' => null, 'name' => 'Vegetables']);
$table->save($vegetables);

// Inserting a new node into tree.
$node = $table->newEntity(['parent_id' => $vegetables->id, 'name' => 'Cucumber']);
$table->save($node);

// Multi trees - Creating a new tree:
$fruits = $table->newEntity(['parent_id' => null, 'name' => 'Fruits']);
$table->save($fruits);

// Moving node (with sub tree).
$node = $table->get($id);
$table->patchEntity($node, ['parent_id' => $newParentId]);
$table->save($node);

// Deleting node or sub tree
$node = $table->get($id);
$table->delete($node);
```


#### Reordering sibling nodes
Sibling nodes are nodes nested under same parent.

                            node_order
    (1)                              
     ├── (2)                         1
     ├── (3)                         2
     ├── (4)                         3
     ├── (5)                         4
     └── (6)                         5

```php
$table = TableRegistry::get('Categories');

$node = $table->get(4);

// Move node one poistion up, down to first or last position in a sub tree
$table->move($node, 'up'); // order of elements in a sub tree: 2, 4, 3, 5, 6
$table->move($node, 'down'); // 2, 3, 4, 5, 6

$table->move($node, 'top'); // 4, 2, 3, 5, 6
$table->move($node, 'bottom'); // 2, 3, 5, 6, 4

// Move node to certian position
$table->move($node, ['to' => 2]); // 2, 4, 3, 5, 6

//Move node before or after certain sibling node.
$table->move($node, ['after' => 5]); // 2, 3, 5, 4, 6
$table->move($node, ['before' => 3]); // 2, 4, 3, 5, 6
```

### Reading tree information.

Behavior recognizes following relations between tree nodes:
- parents (parent),
- children (child),
- branches (branch),
- leaves (leaf),
- siblings (sibling).

and allows to find, count and examine nodes for each relation.

Some finders, methods and options may be available for specific relation(s) only. 


#### Working with parents

Parent is a node that has child nodes. Search starts from the node, continues up and ends at tree root.

    (1)
     └── (2)
          ├── (3)
          └── (4)

```php
$table = TableRegistry::get('Categories');

// Finding parents
$parents = $table->find('parents', ['for' => 4]); // [ 1, 2, 4]
$parents = $table->find('parents', ['for' => 4, 'withSelf' => false]); // [1, 2]


// Counting parents.
$node = $table->get(4);
$conut = $table->count($node, 'parents'); // 2
$count = $this->getDepth(4); // 2


// Examining nodes
$node = $table->get(4);
$check = $table->has($node, 'parent'); // true
```


#### Working with children
                            node_order    
    (1)                              
     └── (2)                         
          ├── (3)                    1
          ├── (4)                    2
          ├── (5)                    3
          └── (6)                    4

```php
$table = TableRegistry::get('Categories');

// Finding children
$children = $table->find('children', ['for' => 2]); // [3, 4, 5, 6]
$children = $table->find('children', ['for' => 2, 'withSelf' => true]); // [2, 3, 4, 5, 6]
$children = $table->find('children', ['for' => 1, 'direct' => true]); // [2]

//Filtering list of children by node_order
$children = $table->find('children', ['for' => 2, 'get' => ['from' => 2, 'to' => 3]]); // [4, 5]
$children = $table->find('children', ['for' => 2, 'get' => ['from' => 2]]); // [4, 5, 6]
$children = $table->find('children', ['for' => 2, 'get' => ['to' => 2]]); // [3, 4]

//Get child having lowets / highest / given node_order
$child =  $table->find('child', ['for' => 2, 'get' => 'first']); // 3
$child =  $table->find('child', ['for' => 2, 'get' => 'last']); // 6
$child =  $table->find('child', ['for' => 2, 'get' => 2]); // 4


// Counting children
$node = $table->get(2);
$count = $table->count($node, 'children', 'direct'); // 4
$count = $table->countChildren($node, $direct = true); // 4


// Examining nodes
$node = $table->get(2);
$check = $table->has($node, 'children'); // true
```

<details>
  <summary>Examples with comments.</summary>
  
  ```php
  
    //Return a query to find children for the node
    $children = $table->find('children', ['for' => $nodeId]);

    //Return a query to find children for the node, including node
    $children = $table->find('children', ['for' => $nodeId, 'withSelf' => true ]);
  
    //Return a query to find children for the node, having node order between $i and $j
    $children = $table->find('children', ['for' => $nodeId, 'node_order' => ['from' => $i, 'to' => $j]]);
  
    //Return a query to find children for the node,for the node, having node order higner then $i
    $children = $table->find('children', ['for' => $nodeId, 'node_order' => ['from' => $i]]);
  
    //Return a query to find children for the node,for the node, having node order lower then $i
    $children = $table->find('children', ['for' => $nodeId, 'node_order' => ['to' => $i]]);
  

    //Return a query to find child having lowest node order
    $child =  $table->find('child', ['for' => $nodeId, 'get' => 'first']);
    
    //Return a query to find child having highest node order
    $child =  $table->find('child', ['for' => $nodeId, 'get' => 'last']);

    //Return a query to find child at given position (node order)
    $child =  $table->find('child', ['for' => $nodeId, 'node_order' => $position]);
  
  
  
    $node = $table->get($nodeId);
  
    // Check if node has any children  
    $check = $table->has($node, 'children');

    // Count node children. 
    $count = $table->count($node, 'children');
  
    // Count node children - TreeBehavior compatibile method.
    $count = $table->countChildren($node);
  ```  
</details>


#### Working with branches

Branch is a node that has child nodes. Search starts from thee node and goes down and ends....


    (1)
     └── (2)
     │    └── (3)
     ├── (4)          
     ├── (5)  
     │    └── (6)
     └── (7)  
          └── (8)

Finders and counters uisng 'next' and 'prev' paramaters will look for sibling branch(es) of given node.

```php
$table = TableRegistry::get('Categories');

// Finding branches
$branches = $table->find('branches', ['for' => 1]); // [2, 5, 7]
$branches = $table->find('branches', ['for' => 4, 'get' => 'prev']); // [2]
$branches = $table->find('branches', ['for' => 4, 'get' => 'next']); // [5, 7]

//Get child branches having lowest / highest node_order
$branch = $table->find('branch', ['for' => 1, 'get' => 'first']); // 2
$branch = $table->find('branch', ['for' => 1, 'get' => 'last']); // 7

//Get sibling branches having lower / higher  node_order then given order
$branch = $table->find('branch', ['for' => 4, 'get' => 'prev']); // 2
$branch = $table->find('branch', ['for' => 4, 'get' => 'next']); // 5


// Counting branches
$node = $table->get(4);
$count = $table->count($node, 'branches'); // 0
$count = $table->count($node, 'branches', 'prev'); // 1
$count = $table->count($node, 'branches', 'next'); // 2
$count = $table->count($node, 'branches', 'siblings');  // implement


// Examining nodes
$node = $table->get(4);
$check = $table->has($node, 'branches'); // false
$check = $table->has($node, 'branches', 'prev'); // true
$check = $table->has($node, 'branches', 'next'); // true
$check = $table->has($node, 'branches', 'sibling'); // true

$check = $table->is($node, 'branch'); // implement

```
<details>
  <summary>Examples with comments.</summary>
  
  ```php

    // Return a query to find branches for the node
    $branches = $table->find('branches', ['for' => $nodeId]); 
  
    // Return a query to find sibling branches having node_order lower then node;
    // sibling branches are branches nested under same direct parent as $nodeId.
    $branches = $table->find('branches', ['for' => $nodeId, 'get' => 'prev']); 
  
    // Return a query to find sibling branches having node_order higher then node;
    // sibling branches are branches nested under same direct parent as $nodeId.
    $branches = $table->find('branches', ['for' => $nodeId, 'get' => 'next']); 

    // Return a query to find branch having lowest node order and which is directly nested under $nodeId.
    $branch = $table->find('branch', ['for' => $nodeId, 'get' => 'first']);
  
    // Return a query to find branch having highest node order and which is directly nested under $nodeId.
    $branch = $table->find('branch', ['for' => $nodeId, 'get' => 'last']);
  
    // Return a query to find first sibling branch having node_order lower then $nodeId;
    // sibling branches are branches nested under same direct parent as $nodeId.
    $branch = $table->find('branch', ['for' => $nodeId, 'get' => 'prev']);
  
    // Return a query to find first sibling branch having node_order higher then $nodeId;
    // sibling branches are branches nested under same direct parent as $nodeId.
    $branch = $table->find('branch', ['for' => $nodeId, 'get' => 'next']);


    $node = $table->get($nodeId);
  
    // Count branches which are direct children of node .
    $count = $table->count($node, 'branches');
  
    // Chount sibling branches having noder_order lower then given node.
    $count = $table->count($node, 'branches', 'prev');
  
    // Chount sibling branches having noder_order higher then given node.
    $count = $table->count($node, 'branches', 'next');
  
    // Chount sibling branches for given node.
    $count = $table->count($node, 'branches', 'siblings');

  
    // Check if there are any branches nested under the node
    $check = $table->has($node, 'branches');
  
    // Check if there are any sibling branches having node_order lower then given node.
    $check = $table->has($node, 'branches', 'prev');
  
    // Check if there are any sibling branches having node_order higher then given node.
    $check = $table->has($node, 'branches', 'next');
  
  
    $check = $table->has($node, 'branches', 'sibling');

    // Check if node is a branch. Same as $table->has($node, 'children');
    $check = $table->is($node, 'branch');

  ```

</details>


#### Working with leaves

Leaf is a node that has no child nodes.


    (1)
     ├── (2)
     ├── (3)  
     │    └── (4)
     ├── (5)  
     └── (6)  

Finders and counters uisng 'next' and 'prev' paramaters will look for sibling  leaf (leaves) of given node.

```php
$table = TableRegistry::get('Categories');

// Finding leaves
$leaves = $table->find('leaves', ['for' => 1]); // [2, 5, 6]
$leaves = $table->find('leaves', ['for' => 3, 'get' => 'prev']); // [2]
$leaves = $table->find('leaves', ['for' => 3, 'get' => 'next']); // [5, 6]

$leaf = $table->find('leaf', ['for' => 1, 'get' => 'first']); // 2
$leaf = $table->find('leaf', ['for' => 1, 'get' => 'last']); // 6
$leaf = $table->find('leaf', ['for' => 3, 'get' => 'prev']); // 2
$leaf = $table->find('leaf', ['for' => 3, 'get' => 'next']); // 5


// Counting leaves
$node = $table->get(5);
$count = $table->count($node, 'leaves'); // 0
$count = $table->count($node, 'leaves', 'prev'); // 2
$count = $table->count($node, 'leaves', 'next'); // 6
$count = $table->count($node, 'leaves', 'siblings'); // 2


// Examining nodes
$node = $table->get(5);
$check = $table->has($node, 'leaves'); // false
$check = $table->has($node, 'leaves', 'prev'); // true
$check = $table->has($node, 'leaves', 'next'); // true
$check = $table->has($node, 'leaves', 'sibling'); // true

$check = $table->is($node, 'leaf'); // implement
```
<details>
  <summary>Examples with comments.</summary>
  
  ```php

    // Return a query to find leaves for the node
    $branches = $table->find('leaves', ['for' => $nodeId]); 
  
    // Return a query to find sibling leaves having node_order lower then node;
    // sibling leaves are leaves nested under same direct parent as $nodeId.
    $branches = $table->find('leaves', ['for' => $nodeId, 'get' => 'prev']); 
  
    // Return a query to find sibling leaves having node_order higher then node;
    // sibling leaves are leaves nested under same direct parent as $nodeId.
    $branches = $table->find('leaves', ['for' => $nodeId, 'get' => 'next']); 

    // Return true or false depdning if node has sibling leaves
    // sibling leaves are leaves nested under same direct parent as $nodeId.
    $branches = $table->find('leaves', ['for' => $nodeId, 'get' => 'siblings']); 
  
    // Return a query to find leaf having lowest node order and which is directly nested under $nodeId.
    $branch = $table->find('leaf', ['for' => $nodeId, 'get' => 'first']);
  
    // Return a query to find leaf having highest node order and which is directly nested under $nodeId.
    $branch = $table->find('leaf', ['for' => $nodeId, 'get' => 'last']);
  
    // Return a query to find first sibling leaf having node_order lower then $nodeId;
    // sibling leaves are leaves nested under same direct parent as $nodeId.
    $branch = $table->find('leaf', ['for' => $nodeId, 'get' => 'prev']);
  
    // Return a query to find first sibling leaf having node_order higher then $nodeId;
    // sibling leaves are leaves nested under same direct parent as $nodeId.
    $branch = $table->find('leaf', ['for' => $nodeId, 'get' => 'next']);


    $node = $table->get($nodeId);
  
    // Count leaves which are direct children of node .
    $count = $table->count($node, 'leaves');
  
    // Chount sibling leaves having noder_order lower then given node.
    $count = $table->count($node, 'leaves', 'prev');
  
    // Chount sibling leaves having noder_order higher then given node.
    $count = $table->count($node, 'leaves', 'next');
  
    // Chount sibling leaves for given node.
    $count = $table->count($node, 'leaves', 'siblings');

  
    // Check if there are any leaves nested under the node
    $check = $table->has($node, 'laves');
  
    // Check if there are any sibling leaves having node_order lower then given node.
    $check = $table->has($node, 'leaves', 'prev');
  
    // Check if there are any sibling leaves having node_order higher then given node.
    $check = $table->has($node, 'leaves', 'next');
  
    // Check if node has any sibling leaves.
    $check = $table->has($node, 'leaves', 'sibling');

    // Check if node is a leaf.
    $check = $table->is($node, 'leaf');

  ```

</details>

#### Working with siblings

Siblings are nodes nested under same immediate parent.


    (1)
     ├── (2)
     ├── (3)  
     │    └── (4)
     ├── (5)  
     └── (6)  

Depending on the value of the 'get' parameter, context in which $id is used will change. <br />
For 'next' and 'previous' then finder will get sibling(s) having node_order higher or lower then given

```php
$table = TableRegistry::get('Categories');

// Finding siblings
$siblings = $table->find('siblings', ['for' => 3]); // [2, 5, 6]
$siblings = $table->find('siblings', ['for' => 3, 'get' => 'prev']); // [2]
$siblings = $table->find('siblings', ['for' => 3, 'get' => 'next']); // [5, 6]

$sibling = $table->find('sibling', ['for' => 3, 'get' => 'first']); // 2
$sibling = $table->find('sibling', ['for' => 3, 'get' => 'last']); // 6
$sibling = $table->find('sibling', ['for' => 3, 'get' => 'prev']); // 2
$sibling = $table->find('sibling', ['for' => 3, 'get' => 'next']); // 5


// Counting siblings
$node = $table->get(3);
$count = $table->count($node, 'siblings'); // 3
$count = $table->count($node, 'siblings', 'prev'); // 1
$count = $table->count($node, 'siblings', 'next'); // 2


// Examining nodes
$node = $table->get(3);
$check = $table->has($node, 'siblings'); // true
$count = $table->has($node, 'siblings', 'prev'); // 1
$count = $table->has($node, 'siblings', 'next'); // 2

$check = $table->is($node, 'sibling'); // implement
```
<details>
  <summary>Examples with comments.</summary>
  
  ```php
    // Depending on the value of the 'get' parameter, context in which $id is used will change.
    // For 'next' and 'previous' then finder will get sibling(s) having node_order higher or lower then given
  

    // Return a query to find siblings of the node
    $branches = $table->find('siblings', ['for' => $nodeId]); 
  
    // Return a query to find siblings having node_order lower then node;
    $branches = $table->find('siblings', ['for' => $nodeId, 'get' => 'prev']); 
  
    // Return a query to find siblings having node_order higher then node;
    $branches = $table->find('siblings', ['for' => $nodeId, 'get' => 'next']); 
  
  
    // Return a query to find sibling having lowest node order and which is directly nested under $nodeId.
    $branch = $table->find('sibling', ['for' => $nodeId, 'get' => 'first']);
  
    // Return a query to find sibling having highest node order and which is directly nested under $nodeId.
    $branch = $table->find('sibling', ['for' => $nodeId, 'get' => 'last']);
  
    // Return a query to find first sibling having node_order lower then $nodeId;
    $branch = $table->find('sibling', ['for' => $nodeId, 'get' => 'prev']);
  
    // Return a query to find first sibling having node_order higher then $nodeId;
    $branch = $table->find('sibling', ['for' => $nodeId, 'get' => 'next']);


    $node = $table->get($nodeId);
  
    // Count leaves which are direct children of node .
    $count = $table->count($node, 'leaves');
  
    // Chount sibling leaves having noder_order lower then given node.
    $count = $table->count($node, 'leaves', 'prev');
  
    // Chount sibling leaves having noder_order higher then given node.
    $count = $table->count($node, 'leaves', 'next');
  
    // Chount sibling leaves for given node.
    $count = $table->count($node, 'leaves', 'siblings');

  
    // Check if there are any leaves nested under the node
    $check = $table->has($node, 'laves');
  
    // Check if there are any sibling leaves having node_order lower then given node.
    $check = $table->has($node, 'leaves', 'prev');
  
    // Check if there are any sibling leaves having node_order higher then given node.
    $check = $table->has($node, 'leaves', 'next');
  
    // Check if node has any sibling leaves.
    $check = $table->has($node, 'leaves', 'sibling');

    // Check if node is a leaf.
    $check = $table->is($node, 'leaf');

  ```

</details>
