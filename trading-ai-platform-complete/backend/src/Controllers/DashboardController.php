<?php
namespace App\Controllers;

use App\Database;
use PDO;
use Throwable;

class DashboardController
{
    public static function show(): void
    {
        $userId = AuthController::authenticatedUserId();

        if ($userId === null) {
            \jsonResponse(['error' => 'Sessão inválida ou expirada.'], 401);
            return;
        }

        try {
            $db = Database::connection();

            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM strategies WHERE user_id = :uid'
            );
            $stmt->execute(['uid' => $userId]);
            $strategies = (int) $stmt->fetchColumn();

            $stmt = $db->prepare(
                "SELECT COUNT(*)
                 FROM orders
                 WHERE user_id = :uid
                   AND connector = 'paper'
                   AND LOWER(status) IN ('open', 'active', 'pending')"
            );
            $stmt->execute(['uid' => $userId]);
            $activePositions = (int) $stmt->fetchColumn();

            $stmt = $db->prepare(
                "SELECT COALESCE(SUM(
                    CASE
                        WHEN jsonb_exists(metadata, 'pnl')
                             AND metadata->>'pnl' ~ '^-?[0-9]+([.][0-9]+)?$'
                        THEN (metadata->>'pnl')::numeric
                        ELSE 0
                    END
                 ), 0)
                 FROM orders
                 WHERE user_id = :uid AND connector = 'paper'"
            );
            $stmt->execute(['uid' => $userId]);
            $paperPnl = (float) $stmt->fetchColumn();

            $stmt = $db->prepare(
                'SELECT status
                 FROM subscriptions
                 WHERE user_id = :uid
                 ORDER BY created_at DESC
                 LIMIT 1'
            );
            $stmt->execute(['uid' => $userId]);
            $subscription = $stmt->fetchColumn();

            \jsonResponse([
                'mode' => getenv('TRADING_MODE') ?: 'paper',
                'live_enabled' => filter_var(
                    getenv('ENABLE_LIVE_TRADING') ?: 'false',
                    FILTER_VALIDATE_BOOL
                ),
                'stats' => [
                    'strategies' => $strategies,
                    'active_positions' => $activePositions,
                    'paper_pnl' => $paperPnl,
                    'subscription' => $subscription === false
                        ? 'trial'
                        : $subscription
                ]
            ]);
        } catch (Throwable $e) {
            error_log('Dashboard error: ' . $e->getMessage());
            \jsonResponse([
                'error' => 'Não foi possível carregar os dados do painel.'
            ], 500);
        }
    }
}
