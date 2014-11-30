<?php

use infuse\Database;
use Pimple\Container;
use app\twitter\libs\TwitterService;
use app\twitter\models\TwitterProfile;

class TwitterServiceTest extends \PHPUnit_Framework_TestCase
{
    public static $profile;

    public static function setUpBeforeClass()
    {
        Database::delete('TwitterProfiles', [ 'id' => 1 ]);

        self::$profile = new TwitterProfile();
        self::$profile->grantAllPermissions();
        self::$profile->create([
            'id' => 1,
            'access_token' => 'reftoken',
            'access_token_secret' => 'refsecret', ]);
    }

    public static function tearDownAfterClass()
    {
        if (self::$profile) {
            self::$profile->grantAllPermissions();
            self::$profile->delete();
        }
    }

    public function testSetAccessTokenFromProfile()
    {
        $app = new Container();
        $twitter = Mockery::mock('Api');
        $twitter->shouldReceive('setTokens')->withArgs([ 'token', 'secret' ])->once();
        $app[ 'twitter' ] = $twitter;

        $service = new TwitterService($app);

        $profile = new TwitterProfile();
        $profile->access_token = 'token';
        $profile->access_token_secret = 'secret';

        $this->assertEquals($service, $service->setAccessTokenFromProfile($profile));
    }

    public function testSetAccessTokenFromProfileReference()
    {
        $app = new Container();
        $twitter = Mockery::mock('Api');
        $twitter->shouldReceive('setTokens')->withArgs([ 'reftoken', 'refsecret' ])->once();
        $app[ 'twitter' ] = $twitter;

        $service = new TwitterService($app);

        $profile = new TwitterProfile();
        $profile->most_recently_referenced_by = 1;

        $this->assertEquals($service, $service->setAccessTokenFromProfile($profile));
    }

    public function testApi()
    {
        $app = new Container();
        $twitter = Mockery::mock('Api');
        $twitter->shouldReceive('delete')->withArgs([ '/test', [ 'test' => true ] ])
            ->andReturn([ 'worked' => true ])->once();
        $app[ 'twitter' ] = $twitter;

        $service = new TwitterService($app);

        $this->assertEquals([ 'worked' => true ], $service->api('/test', 'delete', [ 'test' => true ]));
    }

    public function testApiException()
    {
        $app = new Container();
        $twitter = Mockery::mock('Api');
        $e = new Exception();
        $twitter->shouldReceive('get')->withArgs([ '/test', null ])
            ->andThrow($e)->once();
        $app[ 'twitter' ] = $twitter;
        $logger = Mockery::mock('Logger');
        $logger->shouldReceive('error')->withArgs([ $e ])->once();
        $app[ 'logger' ] = $logger;

        $service = new TwitterService($app);

        $this->assertFalse($service->api('/test', 'get'));
    }

    public function testApiRateLimitError()
    {
        $response = new stdClass();
        $error = new stdClass();
        $error->code = 88;
        $response->errors = [ $error ];

        $app = new Container();
        $twitter = Mockery::mock('Api');
        $twitter->shouldReceive('get')->withArgs([ '/test', null ])
            ->andReturn($response)->once();
        $app[ 'twitter' ] = $twitter;
        $logger = Mockery::mock('Logger');
        $logger->shouldReceive('error')->withArgs([ 'Hit Twitter rate limit on /test with params: null' ])->once();
        $app[ 'logger' ] = $logger;

        $service = new TwitterService($app);

        $this->assertEquals($response, $service->api('/test', 'get'));
    }

    public function testApiAccessTokenExpiredError()
    {
        $response = new stdClass();
        $error = new stdClass();
        $error->code = 89;
        $response->errors = [ $error ];

        $profile = Mockery::mock('app\twitter\models\TwitterProfile');
        $profile->shouldReceive('get')->andReturn([ 'access_token' => 'test', 'access_token_secret' => 'test' ])->once();
        $profile->shouldReceive('grantAllPermissions')->once();
        $profile->shouldReceive('set')->withArgs([ [ 'access_token' => '', 'access_token_secret' => '' ] ])->once();
        $profile->shouldReceive('enforcePermissions')->once();

        $app = new Container();
        $twitter = Mockery::mock('Api');
        $twitter->shouldReceive('setTokens')->once();
        $twitter->shouldReceive('get')->withArgs([ '/test', null ])
            ->andReturn($response)->once();
        $app[ 'twitter' ] = $twitter;

        $service = new TwitterService($app);
        $service->setAccessTokenFromProfile($profile);

        $this->assertEquals($response, $service->api('/test', 'get'));
    }

    public function testApiMustVerifyError()
    {
        $response = new stdClass();
        $error = new stdClass();
        $error->code = 231;
        $response->errors = [ $error ];

        $app = new Container();
        $twitter = Mockery::mock('Api');
        $twitter->shouldReceive('get')->withArgs([ '/test', null ])
            ->andReturn($response)->once();
        $app[ 'twitter' ] = $twitter;
        $logger = Mockery::mock('Logger');
        $logger->shouldReceive('error')->withArgs([ 'User must verify twitter login on /test with params: null' ])->once();
        $app[ 'logger' ] = $logger;

        $service = new TwitterService($app);

        $this->assertEquals($response, $service->api('/test', 'get'));
    }
}
