<?php

use Phinx\Migration\AbstractMigration;

class Retweet extends AbstractMigration
{
    /**
     * Change Method.
     */
    public function change()
    {
        if( !$this->hasTable( 'Retweets' ) )
        {
            $table = $this->table( 'Retweets', [ 'id' => false, 'primary_key' => [ 'tweet_id', 'twitter_id' ] ] );
            $table->addColumn( 'tweet_id', 'biginteger', [ 'length' => 20 ] )
                  ->addColumn( 'twitter_id', 'biginteger', [ 'length' => 20 ] )
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