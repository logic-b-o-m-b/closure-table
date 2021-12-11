<?php
use Migrations\AbstractMigration;

class CreateCategoriesClosure extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-change-method
     * @return void
     */
    public function change()
    {
        $table = $this->table('categories_closure', [
            'id' => false,
            'primary_key' => ['id']
        ]);

        $table->addColumn('id', 'integer', [
            'signed' => false,
            'identity' => true
        ]);

        $table->addColumn('parent_id', 'integer', [
            'default' => null,
            'null' => true,
        ]);

        $table->addColumn('child_id', 'integer', [
            'default' => null,
            'null' => false,
        ]);

        $table->addColumn('depth', 'integer', [
            'default' => null,
            'null' => false,
            'signed' => false
        ]);

        $table->addColumn('node_order', 'integer', [
            'default' => null,
            'null' => true,
        ]);
        
        $table->addForeignKey('parent_id', 'categories', 'id');
        $table->addForeignKey('child_id', 'categories', 'id');
        
        $table->addIndex(['depth']);
        $table->addIndex(['node_order']);
       

        $table->create();
    }
}
