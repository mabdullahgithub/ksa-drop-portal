<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| The phone app is one screen: the portal's rider app (React), shown in a
| full-screen web view. Everything it shows comes from the portal's
| /rider/api/* — nothing is stored or decided here.
*/

$shell = fn (?string $activation = null) => response()
    ->view('app', ['boot' => [
        'apiBase' => config('rider.portal_url'),
        // NativePHP serves public/ from its own address (ASSET_URL).
        'assetBase' => rtrim(asset(''), '/'),
        'activation' => $activation,
    ]])
    ->header('Cache-Control', 'no-store');

Route::get('/', fn () => $shell());

// The sign-in link the office sends ({portal}/rider/activate/{token}) opens
// the app here once the portal's address is the app's deep link host.
Route::get('/rider/activate/{token}', fn (string $token) => $shell($token))
    ->where('token', '[A-Za-z0-9]{48}');

// A photo the phone's camera just took (resources/js/camera.ts). NativePHP
// saves it in the app's own cache and reports the path; the web view can't
// read files, so it asks for the bytes here. Only a camera photo is served,
// and it is removed once handed over — the app keeps the copy it uploads.
Route::get('/captured-photo', function (Request $request) {
    $path = realpath((string) $request->query('path')) ?: '';

    abort_unless(
        $path !== ''
            && preg_match('/^captured_(photo_)?\d+\.jpe?g$/i', basename($path))
            && @getimagesize($path) !== false,
        404,
    );

    return response()->file($path, ['Cache-Control' => 'no-store'])->deleteFileAfterSend();
});

// Any other rider link the phone hands over: just open the app.
Route::redirect('/rider/{any?}', '/')->where('any', '.*');
