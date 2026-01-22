<?php

/**
 * Interface PaymentGatewayInterface
 * 
 * Defines the standard methods that any Payment Gateway Service must implement.
 * This allows swapping between Simulation, Stripe, Omise, or Bank APIs easily.
 */
interface PaymentGatewayInterface
{

    /**
     * Process a money transfer to a user's bank account.
     * 
     * @param string $bank_name      Name of the destination bank
     * @param string $account_number Destination account number
     * @param float  $amount         Amount to transfer
     * @param string $ref_order_id   Internal reference ID (e.g., Withdrawal Request ID)
     * 
     * @return array  ['success' => bool, 'message' => string, 'transaction_id' => string|null]
     */
    public function transfer(string $bank_name, string $account_number, float $amount, string $ref_order_id): array;
}
