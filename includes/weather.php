<?php
// includes/weather.php

function fetchWeather($apiKey, $location) {
    $cacheFile = __DIR__ . "/weather_cache.json";
    $cacheDuration = 3600; // Cache for 1 hour

    // Check if cached data is still valid
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheDuration) {
        return json_decode(file_get_contents($cacheFile), true);
    }

    // Fetch fresh data from the API
    $url = "http://api.openweathermap.org/data/2.5/weather?q={$location}&appid={$apiKey}&units=metric";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) {
        throw new Exception("Failed to fetch weather data.");
    }

    $data = json_decode($response, true);

    // Cache the data
    file_put_contents($cacheFile, json_encode($data));

    return $data;
}
?>