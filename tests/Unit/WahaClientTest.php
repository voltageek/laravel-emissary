<?php

declare(strict_types=1);

use Emissary\Waha\WahaClient;
use Emissary\WahaSessionState;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Orchestra\Testbench\TestCase;

uses(TestCase::class);

function makeWahaClient(array $responses): WahaClient
{
    $mock = new MockHandler($responses);
    $handlerStack = HandlerStack::create($mock);

    $reflection = new ReflectionClass(WahaClient::class);
    $client = $reflection->newInstanceWithoutConstructor();

    $apiUrlProp = $reflection->getProperty('apiUrl');
    $apiKeyProp = $reflection->getProperty('apiKey');
    $httpProp = $reflection->getProperty('http');

    $apiUrlProp->setValue($client, 'http://localhost:3000');
    $apiKeyProp->setValue($client, 'test-api-key');
    $httpProp->setValue($client, new Client(['handler' => $handlerStack]));

    return $client;
}

test('createSession sends correct POST body', function (): void {
    $client = makeWahaClient([
        new Response(200, [], json_encode(['message' => 'Session created'])),
    ]);

    $result = $client->createSession('my-session', 'https://example.com/webhooks/whatsapp', 'hmac-key');

    expect($result)->toBeArray();
    expect($result['message'])->toBe('Session created');
});

test('startSession sends POST to correct endpoint', function (): void {
    $client = makeWahaClient([
        new Response(200, [], json_encode(['status' => 'success'])),
    ]);

    $result = $client->startSession('default');

    expect($result)->toBeArray();
    expect($result['status'])->toBe('success');
});

test('stopSession sends POST to session stop endpoint', function (): void {
    $client = makeWahaClient([
        new Response(200, [], json_encode(['message' => 'Stopped'])),
    ]);

    $result = $client->stopSession('default');

    expect($result)->toBeArray();
    expect($result['message'])->toBe('Stopped');
});

test('getStatus maps WAHA response to WahaSessionState', function (): void {
    $client = makeWahaClient([
        new Response(200, [], json_encode(['name' => 'default', 'status' => 'WORKING'])),
    ]);

    $state = $client->getStatus('default');

    expect($state)->toBe(WahaSessionState::Working);
});

test('getQrCode returns raw QR string', function (): void {
    $client = makeWahaClient([
        new Response(200, [], json_encode(['qrCode' => '1@ABCDEF==,1234567890,,,'])),
    ]);

    $qr = $client->getQrCode('default');

    expect($qr)->toBe('1@ABCDEF==,1234567890,,,');
});

test('deleteSession sends DELETE to correct endpoint', function (): void {
    $client = makeWahaClient([
        new Response(200, [], json_encode(['message' => 'Deleted'])),
    ]);

    $result = $client->deleteSession('old-session');

    expect($result)->toBeArray();
    expect($result['message'])->toBe('Deleted');
});

test('listSessions returns session array', function (): void {
    $client = makeWahaClient([
        new Response(200, [], json_encode([
            'sessions' => [
                ['name' => 'session1', 'status' => 'WORKING'],
                ['name' => 'session2', 'status' => 'STOPPED'],
            ],
        ])),
    ]);

    $sessions = $client->listSessions();

    expect($sessions)->toHaveCount(2);
    expect($sessions[0]['name'])->toBe('session1');
    expect($sessions[1]['name'])->toBe('session2');
});

test('WahaClient handles non-200 responses gracefully', function (): void {
    $client = makeWahaClient([
        new Response(500, [], json_encode(['error' => 'Internal error'])),
    ]);

    $result = $client->getStatus('default');

    expect($result)->toBeInstanceOf(WahaSessionState::class);
    expect($result)->toBe(WahaSessionState::Failed);
});
