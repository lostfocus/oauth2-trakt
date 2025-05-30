<?php namespace Lostfocus\OAuth2\Client\Test\Provider;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Lostfocus\OAuth2\Client\Provider\Trakt;
use Lostfocus\OAuth2\Client\Provider\TraktResourceOwner;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Random\RandomException;

class TraktTest extends TestCase
{
    protected Trakt $provider;

    public function tearDown(): void
    {
        parent::tearDown();
    }

    public function testAuthorizationUrl(): void
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);

        $this->assertIsArray($uri);
        $this->assertArrayHasKey('query', $uri);
        assert(array_key_exists('query', $uri));

        parse_str($uri['query'], $query);

        $this->assertArrayHasKey('client_id', $query);
        $this->assertArrayHasKey('redirect_uri', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertArrayHasKey('response_type', $query);
    }

    public function testGetAuthorizationUrl(): void
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);

        $this->assertIsArray($uri);
        $this->assertArrayHasKey('path', $uri);
        assert(array_key_exists('path', $uri));

        $this->assertEquals('/oauth/authorize', $uri['path']);
    }

    public function testGetBaseAccessTokenUrl(): void
    {
        $params = [];

        $url = $this->provider->getBaseAccessTokenUrl($params);
        $uri = parse_url($url);

        $this->assertIsArray($uri);
        $this->assertArrayHasKey('path', $uri);
        assert(array_key_exists('path', $uri));

        $this->assertEquals('/oauth/token', $uri['path']);
    }

    /**
     * @throws GuzzleException
     * @throws IdentityProviderException
     * @throws Exception
     */
    public function testGetAccessToken(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $responseBody = $this->createMock(StreamInterface::class);
        $responseBody->method('__toString')->willReturn(
            '{"access_token": "mock_access_token","token_type": "bearer","expires_in": 3600,"refresh_token": "mock_refresh_token","scope": "public","created_at": ' .
            time() . '}'
        );
        $response->method('getBody')->willReturn($responseBody);
        $response->method('getHeader')->willReturn(['content-type' => 'application/json']);

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())->method('send')->willReturn($response);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $this->assertInstanceOf(AccessToken::class, $token);

        $this->assertEquals('mock_access_token', $token->getToken());
        $this->assertLessThanOrEqual(time() + 3600, $token->getExpires());
        $this->assertGreaterThanOrEqual(time(), $token->getExpires());
        $this->assertEquals('mock_refresh_token', $token->getRefreshToken());
        $this->assertNull($token->getResourceOwnerId());
    }

    /**
     * @throws GuzzleException
     * @throws IdentityProviderException
     * @throws Exception
     * @throws RandomException
     */
    public function testUserData(): void
    {
        $username = uniqid('', true);
        $name = uniqid('', true);
        $avatarUrl = uniqid('', true);
        $id = random_int(1000, 9999);

        $postResponse = $this->createMock(ResponseInterface::class);

        $responseBody = $this->createMock(StreamInterface::class);
        $responseBody->method('__toString')->willReturn(
            '{"access_token":"mock_access_token","authentication_token":"","code":"","expires_in":3600,"refresh_token":"mock_refresh_token","scope":"","state":"","token_type":""}');


        $postResponse->method('getBody')->willReturn($responseBody);
        $postResponse->method('getHeader')->willReturn(['content-type' => 'json']);

        $userResponseBody = $this->createMock(StreamInterface::class);
        $userResponseBody->method('__toString')->willReturn(
            '{"user": {"username": "' . $username . '","name": "' . $name . '","ids": {"slug": "' . $id .
            '"},"images": {"avatar": {"full": "' . $avatarUrl . '"}}}}'
        );

        $userResponse = $this->createMock(ResponseInterface::class);
        $userResponse->method('getBody')->willReturn($userResponseBody);
        $userResponse->method('getHeader')->willReturn(['content-type' => 'application/json']);

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->atLeast(2))->method('send')->willReturn($postResponse, $userResponse);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $this->assertInstanceOf(AccessToken::class, $token);
        $user = $this->provider->getResourceOwner($token);

        $this->assertInstanceOf(TraktResourceOwner::class, $user);

        $this->assertEquals($username, $user->getUsername());
        $this->assertEquals($username, $user->toArray()['user']['username']);
        $this->assertEquals($name, $user->getName());
        $this->assertEquals($name, $user->toArray()['user']['name']);
        $this->assertEquals($avatarUrl, $user->getAvatarUrl());
        $this->assertEquals($avatarUrl, $user->toArray()['user']['images']['avatar']['full']);
        $this->assertEquals($id, $user->getId());
        $this->assertEquals($id, $user->toArray()['user']['ids']['slug']);
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function testExceptionThrownWhenErrorObjectReceived(): void
    {
        $this->expectException(IdentityProviderException::class);
        $message = uniqid('', true);

        $responseBody = $this->createMock(StreamInterface::class);
        $responseBody->method('__toString')->willReturn(
            '{"error": {"code": "request_token_expired", "message": "' . $message . '"}}'
        );

        $postResponse = $this->createMock(ResponseInterface::class);
        $postResponse->method('getBody')->willReturn($responseBody);
        $postResponse->method('getHeader')->willReturn(['content-type' => 'application/json']);
        $postResponse->method('getStatusCode')->willReturn(500);

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())->method('send')->willReturn($postResponse);
        $this->provider->setHttpClient($client);

        $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }

    protected function setUp(): void
    {
        $this->provider = new Trakt(
            [
                'clientId' => 'mock_client_id',
                'clientSecret' => 'mock_secret',
                'redirectUri' => 'none',
            ]
        );
    }
}
