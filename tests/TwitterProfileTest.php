<?php

use infuse\Database;

use app\twitter\models\TwitterProfile;
use app\users\models\User;

class TwitterProfileTest extends \PHPUnit_Framework_TestCase
{
    public static $profile;
    public static $twitter;

    public static function setUpBeforeClass()
    {
        Database::delete( 'TwitterProfiles', [ 'id' => 1 ] );
    }

    public static function tearDownAfterClass()
    {
        if (self::$profile) {
            self::$profile->grantAllPermissions();
            self::$profile->delete();
        }
    }

    public function testUserPropertyForProfileId()
    {
        $profile = new TwitterProfile();
        $this->assertEquals( 'twitter_id', $profile->userPropertyForProfileId() );
    }

    public function testApiPropertyMapping()
    {
        $profile = new TwitterProfile();
        $expected = [
            'username' => 'screen_name',
            'name' => 'name',
            'profile_image_url' => 'profile_image_url',
            'description' => 'description',
            'location' => 'location',
            'friends_count' => 'friends_count',
            'followers_count' => 'followers_count',
            'listed_count' => 'listed_count',
            'favourites_count' => 'favourites_count',
            'statuses_count' => 'statuses_count',
            'verified' => 'verified'
        ];
        $this->assertEquals( $expected, $profile->apiPropertyMapping() );
    }

    public function testDaysUntilStale()
    {
        $profile = new TwitterProfile();
        $this->assertEquals( 7, $profile->daysUntilStale() );
    }

    public function testNumProfilesToRefresh()
    {
        $profile = new TwitterProfile();
        $this->assertEquals( 180, $profile->numProfilesToRefresh() );
    }

    public function testUrl()
    {
        $profile = new TwitterProfile();
        $profile->username = 'test';
        $this->assertEquals( 'http://twitter.com/test', $profile->url() );
    }

    public function testProfilePicture()
    {
        $profile = new TwitterProfile();
        $profile->profile_image_url = 'profile_picture_normal';
        $this->assertEquals( 'profile_picture_bigger', $profile->profilePicture() );
    }

    public function testIsLoggedIn()
    {
        $response = new stdClass();
        $response->id = 100;

        $profile = new TwitterProfile( 100 );

        $app = TestBootstrap::app();
        $twitter = Mockery::mock( 'TwitterService' );
        $twitter->shouldReceive( 'setAccessTokenFromProfile' )->withArgs( [ $profile ] )->once();
        $twitter->shouldReceive( 'api' )->withArgs( [ 'account/verify_credentials', 'get' ] )
            ->andReturn( $response )->once();
        $app[ 'twitter_service' ] = $twitter;

        $this->assertTrue( $profile->isLoggedIn() );
    }

    public function testIsNotLoggedIn()
    {
        $response = new stdClass();
        $response->id = 101;

        $profile = new TwitterProfile( 100 );

        $app = TestBootstrap::app();
        $twitter = Mockery::mock( 'TwitterService' );
        $twitter->shouldReceive( 'setAccessTokenFromProfile' )->withArgs( [ $profile ] )->once();
        $twitter->shouldReceive( 'api' )->withArgs( [ 'account/verify_credentials', 'get' ] )
            ->andReturn( $response )->once();
        $app[ 'twitter_service' ] = $twitter;

        $this->assertFalse( $profile->isLoggedIn() );
    }

    // function testIsNotLoggedInError()
    // {
    // 	$response = new stdClass;
    // 	$response->errors = [];

    // 	$profile = new TwitterProfile( 100 );

    // 	$app = TestBootstrap::app();
    // 	$twitter = Mockery::mock( 'TwitterService' );
    // 	$twitter->shouldReceive( 'setAccessTokenFromProfile' )->withArgs( [ $profile ] )->once();
    // 	$twitter->shouldReceive( 'api' )->withArgs( [ 'account/verify_credentials', 'get' ] )
    // 		->andReturn( $response )->once();
    // 	$app[ 'twitter_service' ] = $twitter;

    // 	$logger = Mockery::mock( 'Logger' );
    // 	$logger->shouldReceive( 'error' )->withArgs( [ 'Could not authenticate profile # 100: {}' ] )->once();
    // 	$app[ 'logger' ] = $logger;

    // 	$this->assertFalse( $profile->isLoggedIn() );
    // }

    public function testCreate()
    {
        self::$profile = new TwitterProfile();
        self::$profile->grantAllPermissions();
        $this->assertTrue( self::$profile->create( [
            'id' => 1,
            'name' => 'Jared',
            'access_token' => 'test' ] ) );
        $this->assertGreaterThan( 0, self::$profile->last_refreshed );
    }

    /**
	 * @depends testCreate
	 */
    public function testEdit()
    {
        sleep( 1 );
        $oldTime = self::$profile->last_refreshed;

        self::$profile->grantAllPermissions();
        self::$profile->set( [
            'name' => 'Test' ] );

        $this->assertNotEquals( $oldTime, self::$profile->last_refreshed );
    }

    /**
	 * @depends testCreate
	 */
    public function testRefreshProfile()
    {
        $response = new stdClass();
        $response->screen_name = 'j';
        $response->name = 'Some other Jared';
        $response->location = 'Tulsa';
        $response->followers_count = 100;
        $response->friends_count = 134;
        $response->verified = true;
        $response->profile_image_url = 'profile_picture_normal';

        $app = TestBootstrap::app();
        $twitter = Mockery::mock( 'TwitterService' );
        $twitter->shouldReceive( 'setAccessTokenFromProfile' )->withArgs( [ self::$profile ] )->once();
        $twitter->shouldReceive( 'api' )->withArgs( [ 'users/show', 'get', [ 'user_id' => 1 ] ] )
            ->andReturn( $response )->once();
        $app[ 'twitter_service' ] = $twitter;

        $this->assertTrue( self::$profile->refreshProfile() );

        $expected = [
            'id' => 1,
            'username' => 'j',
            'name' => 'Some other Jared',
            'access_token' => 'test',
            'access_token_secret' => '',
            'profile_image_url' => 'profile_picture_normal',
            'description' => null,
            'location' => 'Tulsa',
            'friends_count' => 134,
            'followers_count' => 100,
            'listed_count' => null,
            'favourites_count' => null,
            'statuses_count' => null,
            'verified' => true,
            'most_recently_referenced_by' => null ];

        $profile = self::$profile->toArray( [ 'last_refreshed', 'created_at', 'updated_at' ] );

        $this->assertEquals( $expected, $profile );
    }

    /**
	 * @depends testRefreshProfile
	 */
    public function testRefreshProfiles()
    {
        $response = new stdClass();
        $response->screen_name = 'j';
        $response->name = 'Some other Jared';
        $response->location = 'Tulsa';
        $response->followers_count = 100;
        $response->friends_count = 134;
        $response->verified = true;
        $response->profile_image_url = 'profile_picture_normal';

        $app = TestBootstrap::app();
        $twitter = Mockery::mock( 'TwitterService' );
        $twitter->shouldReceive( 'setAccessTokenFromProfile' )->once();
        $twitter->shouldReceive( 'api' )->withArgs( [ 'users/show', 'get', [ 'user_id' => 1 ] ] )
            ->andReturn( $response )->once();
        $app[ 'twitter_service' ] = $twitter;

        $t = strtotime( '-1 year' );
        self::$profile->grantAllPermissions();
        self::$profile->set( 'last_refreshed', $t );

        $this->assertTrue( TwitterProfile::refreshProfiles() );

        self::$profile->load();
        $this->assertGreaterThan( $t, self::$profile->last_refreshed );
    }

    /**
	 * @depends testCreate
	 */
    public function testDelete()
    {
        self::$profile->grantAllPermissions();
        $this->assertTrue( self::$profile->delete() );
    }
}
