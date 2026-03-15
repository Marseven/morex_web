<?php

namespace App\Services;

class SmsParser
{
    private const EUR_TO_XAF = 655.957;
    private const USD_TO_XAF = 610.0;

    public function parse(string $message, ?string $sender = null): ?array
    {
        $parsers = [
            [$this, 'parseAirtelMoney'],
            [$this, 'parseBGFIBank'],
            [$this, 'parseUBAGab'],
            [$this, 'parseMoovMoney'],
        ];

        foreach ($parsers as $parser) {
            $result = $parser($message, $sender ?? '');
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    private function parseAirtelMoney(string $body, string $sender): ?array
    {
        $isAirtel = stripos($sender, 'airtel') !== false || stripos($body, 'airtel') !== false;
        if (!$isAirtel) {
            return null;
        }

        $reference = null;
        if (preg_match('/TID[:\s]*([A-Z]{2}\d+\.\d+\.[A-Z]\d+)/i', $body, $tidMatch)) {
            $reference = $tidMatch[1];
        }

        // Credit patterns
        if (preg_match('/compte\s+a\s+[eé]t[eé]\s+credit[eé]\s+de\s+(\d+)\s*(?:FCFA|F)/i', $body, $match)) {
            $emetteur = null;
            if (preg_match('/De:\s*(.+?)(?:\n|TID|$)/', $body, $em)) {
                $emetteur = trim($em[1]);
            }
            return [
                'amount' => (int) $match[1],
                'is_income' => true,
                'beneficiary' => $emetteur,
                'reference' => $reference,
                'source' => 'Airtel Money',
            ];
        }

        if (preg_match('/Recu\s+(\d+(?:\.\d+)?)\s*(?:FCFA|F)\s+du\s+([A-Z0-9]+)/i', $body, $match)) {
            return [
                'amount' => $this->parseAmount($match[1]),
                'is_income' => true,
                'beneficiary' => $match[2],
                'reference' => $reference,
                'source' => 'Airtel Money',
            ];
        }

        if (preg_match('/Vous\s+avez\s+recu\s+(\d+)\s*F\s+du\s+(\d+)\s*,\s*([^.]+)/i', $body, $match)) {
            return [
                'amount' => (int) $match[1],
                'is_income' => true,
                'beneficiary' => trim($match[3]) ?: $match[2],
                'reference' => $reference,
                'source' => 'Airtel Money',
            ];
        }

        // Debit patterns
        if (preg_match('/Vous\s+avez\s+envoye\s+(\d+)\s*F\s+au\s+(\d+)\s+([^.]+?)\.?\s*Frais/i', $body, $match)) {
            return [
                'amount' => (int) $match[1],
                'is_income' => false,
                'beneficiary' => trim($match[3]) ?: $match[2],
                'reference' => $reference,
                'source' => 'Airtel Money',
            ];
        }

        if (preg_match('/RETRAIT\s+de\s+(\d+)\s*(?:FCFA|F)\s+reussi/i', $body, $match)) {
            $dest = null;
            if (preg_match('/vers\s+([A-Z0-9]+)/', $body, $destMatch)) {
                $dest = $destMatch[1];
            }
            return [
                'amount' => (int) $match[1],
                'is_income' => false,
                'beneficiary' => $dest,
                'reference' => $reference,
                'source' => 'Airtel Money',
            ];
        }

        if (preg_match('/Paiement\s+de\s+(\d+)\s*F\s+([A-Z]+)\s+pour/i', $body, $match)) {
            return [
                'amount' => (int) $match[1],
                'is_income' => false,
                'beneficiary' => $match[2],
                'reference' => $reference,
                'source' => 'Airtel Money',
            ];
        }

        return null;
    }

    private function parseBGFIBank(string $body, string $sender): ?array
    {
        $isBGFI = stripos($sender, 'bgfi') !== false
            || stripos($body, 'bgfi') !== false
            || stripos($body, 'bgfibank') !== false;

        if (!$isBGFI) {
            return null;
        }

        if (str_contains($body, 'Chers clients') || str_contains($body, 'travaux')) {
            return null;
        }

        // Card transactions
        if (str_contains($body, 'Carte:') && str_contains($body, 'Montant:')) {
            if (!str_contains($body, 'Transaction approuvee')) {
                return null;
            }

            if (!preg_match('/Montant:\s*([,\d]+(?:\.\d+)?)\s*(EUR|USD|XAF|FCFA)/i', $body, $montantMatch)) {
                return null;
            }

            $amountStr = str_replace(',', '.', $montantMatch[1]);
            $currency = strtoupper($montantMatch[2]);
            $originalAmount = (float) $amountStr;

            if ($currency === 'EUR') {
                $amount = (int) round($originalAmount * self::EUR_TO_XAF);
            } elseif ($currency === 'USD') {
                $amount = (int) round($originalAmount * self::USD_TO_XAF);
            } else {
                $amount = (int) round($originalAmount);
            }

            $merchant = null;
            if (preg_match('/Chez:\s*(.+?)(?:\n|$)/', $body, $merchantMatch)) {
                $merchant = trim($merchantMatch[1]);
            }

            return [
                'amount' => $amount,
                'is_income' => false,
                'beneficiary' => $merchant,
                'source' => 'BGFI Bank',
            ];
        }

        // Account credit
        if (preg_match('/(\d{2}\/\d{2}\/\d{4})\s+(\d{2}:\d{2})\s+Votre\s+compte\s+\d+\*+\d+\s+a\s+ete\s+credite\s+de\s+(\d+)\s*(?:XAF|FCFA)/i', $body, $match)) {
            return [
                'amount' => (int) $match[3],
                'is_income' => true,
                'beneficiary' => 'Virement recu',
                'source' => 'BGFI Bank',
            ];
        }

        // Account debit
        if (preg_match('/(\d{2}\/\d{2}\/\d{4})\s+(\d{2}:\d{2})\s+Votre\s+compte\s+\d+\*+\d+\s+a\s+ete\s+debite\s+de\s+(\d+)\s*(?:XAF|FCFA)/i', $body, $match)) {
            return [
                'amount' => (int) $match[3],
                'is_income' => false,
                'beneficiary' => 'Retrait/Virement',
                'source' => 'BGFI Bank',
            ];
        }

        return null;
    }

    private function parseUBAGab(string $body, string $sender): ?array
    {
        $isUBA = stripos($sender, 'uba') !== false
            || stripos($body, 'uba') !== false
            || stripos($body, 'ubagab') !== false;

        if (!$isUBA) {
            return null;
        }

        $result = $this->parseAmountWithCurrency($body);
        if ($result === null) {
            return null;
        }

        [$amount] = $result;

        $isIncome = (bool) preg_match('/credit|cr\b|recu|received/i', $body);

        return [
            'amount' => $amount,
            'is_income' => $isIncome,
            'beneficiary' => $this->extractBeneficiary($body),
            'reference' => $this->extractReference($body),
            'source' => 'UBA Gabon',
        ];
    }

    private function parseMoovMoney(string $body, string $sender): ?array
    {
        $isMoov = stripos($sender, 'moov') !== false || stripos($body, 'moov') !== false;
        if (!$isMoov) {
            return null;
        }

        if (!preg_match('/(\d[\d\s,\.]*)\s*(?:FCFA|XAF|F)/i', $body, $match)) {
            return null;
        }

        $amount = $this->parseAmount($match[1]);
        $isIncome = (bool) preg_match('/recu|reçu|credit/i', $body);

        return [
            'amount' => $amount,
            'is_income' => $isIncome,
            'beneficiary' => $this->extractBeneficiary($body),
            'reference' => $this->extractReference($body),
            'source' => 'Moov Money',
        ];
    }

    private function parseAmount(string $amountStr): int
    {
        $cleaned = preg_replace('/[^\d.]/', '', str_replace(',', '.', str_replace(' ', '', $amountStr)));
        return (int) round((float) $cleaned);
    }

    private function extractBeneficiary(string $body): ?string
    {
        $patterns = [
            '/(?:de|from)\s+([A-Za-z\s]+?)(?:\s+pour|\s+ref|\.|$)/i',
            '/(?:a|to|vers)\s+([A-Za-z\s]+?)(?:\s+pour|\s+ref|\.|$)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $body, $match)) {
                $beneficiary = trim($match[1]);
                if (strlen($beneficiary) > 2) {
                    return $beneficiary;
                }
            }
        }

        return null;
    }

    private function extractReference(string $body): ?string
    {
        if (preg_match('/(?:TID|ref|reference)[:\s]*([A-Z0-9.]+)/i', $body, $match)) {
            return $match[1];
        }
        return null;
    }

    private function parseAmountWithCurrency(string $body): ?array
    {
        if (preg_match('/(\d[\d\s,\.]*)\s*(?:EUR|€)/i', $body, $match)) {
            $originalAmount = $this->parseAmount($match[1]);
            return [(int) round($originalAmount * self::EUR_TO_XAF), $originalAmount, 'EUR'];
        }

        if (preg_match('/(\d[\d\s,\.]*)\s*(?:USD|\$)/i', $body, $match)) {
            $originalAmount = $this->parseAmount($match[1]);
            return [(int) round($originalAmount * self::USD_TO_XAF), $originalAmount, 'USD'];
        }

        if (preg_match('/(\d[\d\s,\.]*)\s*(?:FCFA|XAF|F\b)/i', $body, $match)) {
            $amount = $this->parseAmount($match[1]);
            return [$amount, $amount, 'XAF'];
        }

        return null;
    }
}
