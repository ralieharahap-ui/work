<?php

namespace App\Http\Controllers\Agent;

use App\Agent\Channels\Telegram\TelegramBot;
use App\Http\Controllers\Controller;
use App\Models\AgentIntegration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Titik masuk pesan Telegram. Terbuka ke internet, maka dijaga tiga lapis:
 * rahasia pada URL, header rahasia dari Telegram, dan pembatasan laju.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, string $organization, string $secret, TelegramBot $bot): JsonResponse
    {
        $record = AgentIntegration::where('organization_id', $organization)
            ->where('key', 'telegram')
            ->first();

        $expected = $record?->credential('webhook_secret') ?: config('agent.telegram.webhook_secret');

        if (! $expected || ! hash_equals((string) $expected, $secret)) {
            abort(404);
        }

        $header = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        if ($header !== '' && ! hash_equals((string) $expected, $header)) {
            abort(403);
        }

        if (RateLimiter::tooManyAttempts('agent-telegram:' . $organization, 120)) {
            return response()->json(['ok' => true, 'throttled' => true]);
        }

        RateLimiter::hit('agent-telegram:' . $organization, 60);

        try {
            $bot->handle($request->all(), $organization);
        } catch (Throwable $e) {
            // Telegram mengulang kiriman bila menerima galat; balas 200 agar
            // satu pesan bermasalah tidak terus diputar ulang.
            report($e);
        }

        return response()->json(['ok' => true]);
    }
}
