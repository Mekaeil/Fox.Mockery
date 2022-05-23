<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse;

class MocksController extends Controller
{
    private string $requestUri;
    private string $serviceName;
    private string $serviceUrl;
    private \GuzzleHttp\Client $client;

    public function __construct()
    {
        $this->serviceName = request('service_name');

        $this->setServiceUrl();

        $this->client = new \GuzzleHttp\Client(['base_uri' => $this->serviceUrl]);

        $this->checkingIsServiceDockerContainerIsAvailable();

        $this->setRequestUri();
    }

    public function index(Request $request): JsonResponse
    {
        $responseBody = $this->sendHttpRequest($request);
        return response()->json(json_decode($responseBody['response']), $responseBody['status']);
    }

    private function checkingIsServiceDockerContainerIsAvailable(): void
    {
        try {
            Http::get($this->serviceUrl);
        } catch (\Exception $exception) {
            $response = [
                'message' => "SERVICE << $this->serviceName >> IS NOT AVAILABLE!",
                'service' => array_merge(getServiceConfig($this->serviceName), [
                    'url' => $this->serviceUrl
                ]),
                'details' => $exception->getMessage(),
            ];
            dd($response);
        }
    }

    private function setServiceUrl(): void
    {
        $serviceConfig = getServiceConfig($this->serviceName);
        $servicePort = $serviceConfig['port'];
        $this->serviceUrl = "http://localhost:{$servicePort}";
    }

    private function setRequestUri(): void
    {
        $baseUrl = url('/') . "/{$this->serviceName}";
        $this->requestUri = ltrim(substr(request()->getUri(), strlen($baseUrl)), '/');
    }

    private function sendHttpRequest(Request $request): array
    {
        $headers = array_merge($request->header(), [
            'allow_redirects' => false,
            'http_errors' => false
        ]);

        $requestBody = empty($request->all()) ? null : json_encode($request->all());
        $newRequest = new \GuzzleHttp\Psr7\Request($request->method(), $this->requestUri, $headers, $requestBody);

        try {
            $response = $this->client->send($newRequest);
        } catch (\Exception $e) {
            return [
                'response' => $e->getResponse()->getBody()->getContents(),
                'status' => $e->getCode()
            ];
        }

        return [
            'response' => $response->getBody(),
            'status' => $response->getStatusCode()
        ];
    }

}
