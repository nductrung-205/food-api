<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/test-gemini-models', function() {
    $apiKey = config('services.google.api_key');
    
    $response = Http::get(
        "https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}"
    );
    
    if ($response->successful()) {
        $models = $response->json();
        $supportedModels = [];
        
        foreach ($models['models'] ?? [] as $model) {
            if (in_array('generateContent', $model['supportedGenerationMethods'] ?? [])) {
                $supportedModels[] = [
                    'name' => $model['name'],
                    'display_name' => $model['displayName'] ?? '',
                    'description' => $model['description'] ?? ''
                ];
            }
        }
        
        return response()->json([
            'total' => count($supportedModels),
            'supported_models' => $supportedModels
        ]);
    }
    
    return response()->json([
        'error' => $response->body()
    ], 500);
});