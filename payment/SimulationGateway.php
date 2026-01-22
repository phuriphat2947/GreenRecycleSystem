<?php
require_once "PaymentGatewayInterface.php";

class SimulationGateway implements PaymentGatewayInterface
{

    public function transfer(string $bank_name, string $account_number, float $amount, string $ref_order_id): array
    {

        return [
            'success' => true,
            'message' => "SIMULATED TRANSFER: Successfully sent " . number_format($amount, 2) . " THB to $bank_name ($account_number).",
            'transaction_id' => "SIM-" . strtoupper(uniqid())
        ];
    }
}
