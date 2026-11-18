<?php

namespace App\Finanzas\Domain;

final class QuarterSummary
{
    /** @param list<QuarterMovement> $movements */
    public function __construct(public readonly array $movements)
    {
    }

    public function incomeCents(): int
    {
        return array_sum(array_map(static fn (QuarterMovement $movement): int => $movement->income ? $movement->baseCents : 0, $this->movements));
    }

    public function expenseCents(): int
    {
        return array_sum(array_map(static fn (QuarterMovement $movement): int => $movement->income ? 0 : $movement->baseCents, $this->movements));
    }

    public function resultCents(): int
    {
        return $this->incomeCents() - $this->expenseCents();
    }

    /** @return array<string, array{output: int, input: int, balance: int}> */
    public function taxes(): array
    {
        $totals = array_fill_keys(['iva', 'igic', 'ipsi'], ['output' => 0, 'input' => 0, 'balance' => 0]);
        foreach ($this->movements as $movement) {
            $totals[$movement->tax][$movement->income ? 'output' : 'input'] += $movement->taxCents;
            $totals[$movement->tax]['balance'] += $movement->income ? $movement->taxCents : -$movement->taxCents;
        }

        return $totals;
    }

    public function surchargeCents(): int
    {
        return array_sum(array_map(static fn (QuarterMovement $movement): int => $movement->surchargeCents, $this->movements));
    }

    public function withholdingCents(): int
    {
        return array_sum(array_map(static fn (QuarterMovement $movement): int => $movement->withholdingCents, $this->movements));
    }
}
