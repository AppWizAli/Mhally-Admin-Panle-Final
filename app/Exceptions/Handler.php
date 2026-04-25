<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render production errors with a browser-safe reference ID and log details.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function render($request, Throwable $e)
    {
        $response = parent::render($request, $e);
        $statusCode = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 500;

        if ($statusCode >= 500 && ! config('app.debug')) {
            $errorId = (string) Str::uuid();

            try {
                Log::error('Production server error', [
                    'error_id' => $errorId,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'method' => $request->method(),
                    'url' => $request->url(),
                    'route' => optional($request->route())->getName(),
                    'admin_id' => $request->hasSession() ? data_get($request->session()->get('admin_user', []), 'id') : null,
                    'ip' => $request->ip(),
                ]);
            } catch (Throwable $loggingException) {
                //
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Server-side error in Mhally Admin.',
                    'error_id' => $errorId,
                ], $statusCode);
            }

            return response($this->productionErrorHtml($errorId), $statusCode);
        }

        return $response;
    }

    private function productionErrorHtml(string $errorId): string
    {
        $escapedErrorId = e($errorId);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Server Error | Mhally Admin</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #f7f8fb; color: #172033; font-family: Arial, Helvetica, sans-serif; }
        main { width: min(100%, 560px); padding: 32px; border: 1px solid #d9dee8; border-radius: 8px; background: #fff; box-shadow: 0 18px 45px rgba(23, 32, 51, 0.08); }
        h1 { margin: 0 0 12px; font-size: 28px; line-height: 1.2; }
        p { margin: 0 0 16px; color: #5e6a7d; font-size: 15px; line-height: 1.6; }
        code { display: block; overflow-wrap: anywhere; padding: 12px; border: 1px solid rgba(20, 122, 100, 0.24); border-radius: 6px; background: rgba(20, 122, 100, 0.08); color: #147a64; font-size: 14px; }
    </style>
</head>
<body>
    <main>
        <h1>Server-side issue in Mhally Admin</h1>
        <p>The request reached the admin panel, but Laravel hit an internal error. The full details were saved in the server log with this reference ID.</p>
        <code>{$escapedErrorId}</code>
    </main>
</body>
</html>
HTML;
    }
}
