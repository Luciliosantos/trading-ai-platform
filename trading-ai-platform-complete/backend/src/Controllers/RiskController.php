<?php
namespace App\Controllers;

use App\Database;
use PDO;
use Throwable;

class RiskController
{
    public static function show(): void
    {
        $userId = AuthController::authenticatedUserId();

        if ($userId === null) {
            \jsonResponse([
                'status' => 'error',
                'message' => 'Faça login para continuar.'
            ], 401);
            return;
        }

        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                'SELECT max_order_value, max_daily_loss, max_open_positions, live_enabled
                 FROM risk_profiles WHERE user_id = :user_id LIMIT 1'
            );
            $stmt->execute(['user_id' => $userId]);
            $risk = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$risk) {
                \jsonResponse([
                    'status' => 'error',
                    'message' => 'Configuração de risco não encontrada.'
                ], 404);
                return;
            }

            $risk['live_enabled'] = false;
            \jsonResponse(['status' => 'ok', 'risk' => $risk]);
        } catch (Throwable $e) {
            error_log('Falha ao consultar risco: ' . $e->getMessage());
            \jsonResponse([
                'status' => 'error',
                'message' => 'Não foi possível consultar o risco.'
            ], 500);
        }
    }

    public static function update(): void
    {
        $userId = AuthController::authenticatedUserId();

        if ($userId === null) {
            \jsonResponse([
                'status' => 'error',
                'message' => 'Faça login para continuar.'
            ], 401);
            return;
        }

        $data = \requestJson();

        $maxOrder = filter_var($data['max_order_value'] ?? null, FILTER_VALIDATE_FLOAT);
        $maxLoss = filter_var($data['max_daily_loss'] ?? null, FILTER_VALIDATE_FLOAT);
        $maxPositions = filter_var($data['max_open_positions'] ?? null, FILTER_VALIDATE_INT);

        if ($maxOrder === false || $maxOrder <= 0 ||
            $maxLoss === false || $maxLoss <= 0 ||
            $maxPositions === false || $maxPositions < 1 || $maxPositions > 100) {
            \jsonResponse([
                'status' => 'error',
                'message' => 'Informe limites de risco válidos.'
            ], 422);
            return;
        }

        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                'UPDATE risk_profiles
                 SET max_order_value = :max_order,
                     max_daily_loss = :max_loss,
                     max_open_positions = :max_positions,
                     live_enabled = FALSE
                 WHERE user_id = :user_id
                 RETURNING user_id'
            );
            $stmt->execute([
                'max_order' => $maxOrder,
                'max_loss' => $maxLoss,
                'max_positions' => $maxPositions,
                'user_id' => $userId
            ]);

            if (!$stmt->fetchColumn()) {
                \jsonResponse([
                    'status' => 'error',
                    'message' => 'Configuração de risco não encontrada.'
                ], 404);
                return;
            }

            \jsonResponse([
                'status' => 'ok',
                'message' => 'Configuração de risco salva.',
                'live_enabled' => false
            ]);
        } catch (Throwable $e) {
            error_log('Falha ao salvar risco: ' . $e->getMessage());
            \jsonResponse([
                'status' => 'error',
                'message' => 'Não foi possível salvar o risco.'
            ], 500);
        }
    }
}
