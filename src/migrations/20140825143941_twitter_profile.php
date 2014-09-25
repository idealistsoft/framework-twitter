<?php

use Phinx\Migration\AbstractMigration;

class TwitterProfile extends AbstractMigration
{
    /**
     * Change Method.
     */
    public function change()
    {
      if( !$this->hasTable( 'TwitterProfiles' ) )
      {
        $table = $this->table( 'TwitterProfiles', [ 'id' => false ] );
        $table->addColumn( 'id', 'biginteger', [ 'length' => 20 ] )
              ->addColumn( 'username', 'string' )
              ->addColumn( 'name', 'string' )
              ->addColumn( 'access_token', 'string' )
              ->addColumn( 'access_token_secret', 'string' )
              ->addColumn( 'profile_image_url', 'string', [ 'null' => true, 'default' => null ] )
              ->addColumn( 'description', 'string', [ 'null' => true, 'default' => null ] )
              ->addColumn( 'location', 'string', [ 'null' => true, 'default' => null ] )
              ->addColumn( 'friends_count', 'integer', [ 'null' => true, 'default' => null ] )
              ->addColumn( 'followers_count', 'integer', [ 'null' => true, 'default' => null ] )
              ->addColumn( 'listed_count', 'integer', [ 'null' => true, 'default' => null ] )
              ->addColumn( 'favourites_count', 'integer', [ 'null' => true, 'default' => null ] )
              ->addColumn( 'statuses_count', 'integer', [ 'null' => true, 'default' => null ] )
              ->addColumn( 'verified', 'boolean' )
              ->addColumn( 'last_refreshed', 'integer' )
              ->addColumn( 'most_recently_referenced_by', 'integer', [ 'null' => true, 'default' => null ] )
              ->addColumn( 'created_at', 'integer' )
              ->addColumn( 'updated_at', 'integer', [ 'null' => true, 'default' => null ] )
              ->create();
      }
    }
    
    /**
     * Migrate Up.
     */
    public function up()
    {
    
    }

    /**
     * Migrate Down.
     */
    public function down()
    {

    }
}