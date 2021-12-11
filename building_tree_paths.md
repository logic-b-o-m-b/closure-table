# Building tree path - Examples

Path is build in 3 steps:
1) Path starts with element itself (parent_id = child_id)
2) then goes up trough every ancestor (parent_id) 
3) and ends at the very top of the tree (parent_id = null)

## Tree root
    
    (1)
     ├── (2)
     └── (3)
          └── (4)
          
          

<details>
  <summary>Path for Node 1</summary>
    For every ancestor value of child_id will be 
constant: **child_id = 1**.

#### Step 1

We start with last elelement in a sub tree -  node itself: **parent_id = 1**, child_id = 1

Depth and node_order have default values: **depth = 0**, **node_order = null**

Path:
<table>
  <tr>
    <th>parent_id</th>
    <th>child_id</th>
    <th>depth</th>
    <th>node_order</th>
  </tr>
  
  <tr>
      <td><b>1</b></td>
      <td><b>1</b></td>
      <td><b>0</b></td>
    <td></td>
  </tr>  
</table>

#### Step 2

Node has no ancestors, this step is ommited.
    
#### Step 3
    
Entry for very top of the tree (**parent_id = null**) is added.
    
Value of parent_id has changed and now is: **parent_id = null**. 
Because parent has changed depth will be incremented and now is: **depth = 1**.

Because "Node 1" is direct and first child of new parent (parent_id = null) node_oder will have a neumeric value: **node_order = 1**;


Complete Path:
<table>
  <tr>
    <th>parent_id</th>
    <th>child_id</th>
    <th>depth</th>
    <th>node_order</th>
  </tr>
  
  <tr>
    <td></td>
      <td><b>1</b></td>
      <td><b>1</b></td>
      <td><b>1</b></td>
  </tr>  
  <tr>
      <td>1</td>
      <td>1</td>
      <td>0</td>
    <td></td>
  </tr>      
</table>
</details>


<details>
  <summary>Path for Node 2</summary>
  
For every node on the path value of child_id will be 
constant: **child_id = 2**.

#### Step 1

We start with last elelement in a sub tree -  node itself: **parent_id = 2**, child_id = 2

Depth and node_order have default values: **depth = 0**, **node_order = null**

Path:
<table>
  <tr>
    <th>parent_id</th>
    <th>child_id</th>
    <th>depth</th>
    <th>node_order</th>
  </tr>
  <tr>
      <td><b>2</b></td>
      <td><b>2</b></td>
      <td><b>0</b></td>
    <td></td>
  </tr>  
</table>

#### Step 2

The node has one ancestor: "Node 1".

A new entry is created for every ancestor:

##### Ancestor: "Node 1"

Value of parent_id has changed and now is: **parent_id = 1**. 

Because parent has changed depth will be incremented and now is: **depth = 1**.

Because "Node 2" is direct and first child of new parent (parent_id = 1) node_oder will have a neumeric value: **node_order = 1**;

Path:
<table>
  <tr>
    <th>parent_id</th>
    <th>child_id</th>
    <th>depth</th>
    <th>node_order</th>
  </tr>
  
  <tr>
      <td><b>1</b></td>
      <td><b>2</b></td>
      <td><b>1</b></td>
      <td><b>1</b></td>
  </tr>  
    
  <tr>
    <td>2</td>
    <td>2</td>
    <td>0</td>
    <td></td>
  </tr>      
</table>

#### Step 3
Entry for very top of the tree (**parent_id = null**) is added.

Because parent has changed depth will be incremented and now is: **depth = 2**.

Because "Node 2" is not a direct child of new parent node_oder will have a null value: **node_order = null**;

Complete Path:
<table>
  <tr>
    <th>parent_id</th>
    <th>child_id</th>
    <th>depth</th>
    <th>node_order</th>
  </tr>
  
  <tr>
    <td></td>
      <td><b>2</b></td>
      <td><b>2</b></td>
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
</table>

</details>


<details>
  <summary>Path for Node 3</summary>
      
For every node on the path value of child_id will be 
constant: **child_id = 3**.

#### Step 1

We start with last elelement in a sub tree -  node itself: **parent_id = 3**, child_id = 3

Depth and node_order have default values: **depth = 0**, **node_order = null**

Path:
<table>
  <tr>
    <th>parent_id</th>
    <th>child_id</th>
    <th>depth</th>
    <th>node_order</th>
  </tr>
  <tr>
      <td><b>3</b></td>
      <td><b>3</b></td>
      <td><b>0</b></td>
    <td></td>
  </tr>  
</table>

#### Step 2
The node has one ancestors: "Node 1".

A new entry is created for every ancestor:

##### Ancestor: "Node 1"
    
Value of parent_id has changed and now is: **parent_id = 1**. 

Because parent has changed depth will be incremented and now is: **depth = 1**.

Because "Node 3" is direct and second child of new parent (parent_id = 1) node_oder will have a neumeric value: **node_order = 2**;

Path:
<table>
  <tr>
    <th>parent_id</th>
    <th>child_id</th>
    <th>depth</th>
    <th>node_order</th>
  </tr>
  
  <tr>
      <td><b>1</b></td>
      <td><b>3</b></td>
      <td><b>1</b></td>
      <td><b></b>2</td>
  </tr>  
    
  <tr>
    <td>3</td>
    <td>3</td>
    <td>0</td>
    <td></td>
  </tr>      
</table>

#### Step 3
Entry for very top of the tree (**parent_id = null**) is added.

Because parent has changed depth will be incremented and now is: **depth = 2**.

Because "Node 2" is not a direct child of new parent node_oder will have a null value: **node_order = null**;
    
Complete Path:
<table>
  <tr>
    <th>parent_id</th>
    <th>child_id</th>
    <th>depth</th>
    <th>node_order</th>
  </tr>
  
  <tr>
    <td></td>
      <td><b>3</b></td>
      <td><b>2</b></td>
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
</table>

</details>

<details>
  <summary>Path for Node 4</summary>
    
For every node on the path value of child_id will be 
constant: **child_id = 4**.

#### Step 1

We start with last elelement in a sub tree -  node itself: **parent_id = 4**, child_id = 4

Depth and node_order have default values: **depth = 0**, **node_order = null**

Path:
<table>
  <tr>
    <th>parent_id</th>
    <th>child_id</th>
    <th>depth</th>
    <th>node_order</th>
  </tr>
  <tr>
      <td><b>4</b></td>
      <td><b>4</b></td>
      <td><b>0</b></td>
    <td></td>
  </tr>  
</table>

#### Step 2
    
The node has two ancestors: "Node 3", "Node 1".
    
A new entry is created for every ancestor:

##### Ancestor "Node 3"
Value of parent_id has changed and now is: **parent_id = 3**. 

Because parent has changed depth will be incremented and now is: **depth = 1**.

Because "Node 4" is direct and first child of new parent (parent_id = 3) node_oder will have a neumeric value: **node_order = 1**;

Path:
<table>
  <tr>
    <th>parent_id</th>
    <th>child_id</th>
    <th>depth</th>
    <th>node_order</th>
  </tr>
  
  <tr>
      <td><b>3</b></td>
      <td><b>4</b></td>
      <td><b>1</b></td>
      <td><b>1</b></td>
  </tr>  
    
  <tr>
    <td>4</td>
    <td>4</td>
    <td>0</td>
    <td></td>
  </tr>      
</table>
    
##### Ancestor "Node 1"
    
Next node up the chain is node with id 1.
Value of parent_id has changed and now is: **parent_id = 1**. 

Because parent has changed depth will be incremented and now is: **depth = 2**.

Because "Node 4" is not a direct child of a new parent (parent_id = 1) node_oder will have a null value : **node_order = null**;

Path:
<table>
  <tr>
    <th>parent_id</th>
    <th>child_id</th>
    <th>depth</th>
    <th>node_order</th>
  </tr>
  
  <tr>
      <td><b>1</b></td>
      <td><b>4</b></td>
      <td><b>2</b></td>
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
        
    
    
#### Step 3
Entry for very top of the tree (**parent_id = null**) is added.

Because parent has changed depth will be incremented and now is: **depth = 3**.

Because "Node 4" is not a direct child of a new parent node_oder will have a null value: **node_order = null**;
    
Complete Path:
<table>
  <tr>
    <th>parent_id</th>
    <th>child_id</th>
    <th>depth</th>
    <th>node_order</th>
  </tr>
  
  <tr>
    <td></td>
      <td><b>4</b></td>
      <td><b>3</b></td>
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

</details>


Paths for all nodes
    
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



[Back](https://github.com/logic-b-o-m-b/closure-table#back)
