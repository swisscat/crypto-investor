<?php

namespace App\Provider\Data;

use Carbon\Carbon;
use DateTime;
use GuzzleHttp\Client;

class RawDataProvider
{
    const DateFormat = 'Y-m-d';

    public function getCachedPrices(DateTime $startDate, DateTime $endDate)
    {
        $dataset = json_decode(file_get_contents(__DIR__.'/price.json'), true);

        $afterStart = false;

        $values = [];

        foreach ($dataset as $date => $value) {
            if ($date == $startDate->format(self::DateFormat)) {
                $afterStart = true;
            }


            if ($afterStart) {
                $values[$date] = $value;
            }

            if ($date == $endDate->format(self::DateFormat)) {
                break;
            }
        }

        return $values;
    }

    public function getPrices(DateTime $startDate, DateTime $endDate)
    {
        $prices = $this->getCachedPrices($startDate, $endDate);

        $cachedPriceDates = array_keys($prices);

        $lastDate = DateTime::createFromFormat(self::DateFormat, end($cachedPriceDates));

        if ($lastDate < $endDate) {
            $client = new Client();
            $res = $client->request('GET', sprintf('https://api.coindesk.com/v1/bpi/historical/close.json?start=%s&end=%s', $lastDate->format(self::DateFormat), $endDate->format(self::DateFormat)));

            $json = json_decode($res->getBody()->getContents(), true);

            $prices = array_merge($prices, $json['bpi']);

            $filePrices = array_merge($prices, json_decode(file_get_contents(__DIR__.'/price.json'), true));

            ksort($filePrices);

            // Cache
            file_put_contents(__DIR__.'/price.json', json_encode($filePrices, JSON_PRETTY_PRINT));
        }

        return $prices;
    }

    public function getCachedProfit(DateTime $startDate, DateTime $endDate)
    {
        $dataset = json_decode(file_get_contents(__DIR__.'/mining.json'), true);

        $afterStart = false;

        $values = [];

        foreach ($dataset as $date => $value) {
            if ($date == $startDate->format(self::DateFormat)) {
                $afterStart = true;
            }


            if ($afterStart) {
                $values[$date] = $value;
            }

            if ($date == $endDate->format(self::DateFormat)) {
                break;
            }
        }

        return $values;
    }

    public function getProfit(DateTime $startDate, DateTime $endDate)
    {
        $profits = $this->getCachedProfit($startDate, $endDate);

        $cachedPriceDates = array_keys($profits);

        $lastDate = DateTime::createFromFormat(self::DateFormat, end($cachedPriceDates));

        if ($lastDate < $endDate) {
            $client = new Client();
            $res = $client->request('GET', 'https://bitinfocharts.com/comparison/bitcoin-mining_profitability.html');

            $body = $res->getBody()->getContents();

            $data = substr($body, $start = (strpos($body, 'new Dygraph(document.getElementById("container")') + strlen('new Dygraph(document.getElementById("container")') + 2), strpos($body, ']], {labels:') + 1 - $start);

            while ($endBracketPos = strpos($data, ']')) {
                $input_line = substr($data, 0, $endBracketPos+1);

                $output_array = [];

                preg_match("/\[new Date\(\"(.*)\"\),(.*)\]/", $input_line, $output_array);

                $dateParsed = DateTime::createFromFormat('Y/m/d', $output_array[1]);

                if ($dateParsed >= $startDate && $dateParsed <= $endDate) {
                    $profits[$dateParsed->format(self::DateFormat)] = (float)$output_array[2];
                }


                $data = substr($data, $endBracketPos+2);
            }

            $fileProfits = array_merge($profits, json_decode(file_get_contents(__DIR__.'/mining.json'), true));

            ksort($fileProfits);

            // Cache
            file_put_contents(__DIR__.'/mining.json', json_encode($fileProfits, JSON_PRETTY_PRINT));
        }

        $prices = $this->getPrices($startDate, $endDate);

        foreach ($profits as $date => $value) {
            $profits[$date] = $value / $prices[$date];
        }

        return $profits;
    }


    public function getHashPrice($btcPrice)
    {
        return 150.0/$btcPrice;
    }

    public function getCumulativeProfit(DateTime $startDate, DateTime $endDate)
    {
        return array_sum($this->getProfit($startDate, $endDate));
    }

    public function getReturnOnInvestment(DateTime $startDate, DateTime $endDate)
    {
        return $this->getReturnReinvestedOnInvestment($startDate, $endDate, 0, 0);
    }

    public function getReturnReinvestedOnInvestment(DateTime $startDate, DateTime $endDate, $percentageKept, $keepReinvestingDays)
    {
        $percentageKept = (100 - $percentageKept) / 100;
        $contractLength = 365;

        $profit = $this->getProfit($startDate, Carbon::now());
        $prices = $this->getPrices($startDate, Carbon::now());

        $investPrice = ['btc' => $this->getHashPrice($prices[$dateKey = $startDate->format(self::DateFormat)]), 'usd' => $this->getHashPrice(1)];

        $amountValues = [$dateKey => ['btc' => 0, 'usd' => 0]];
        $hashrateValues = [$dateKey => 1];

        $startDate = Carbon::instance($startDate);
        $iterationDate = Carbon::instance($startDate);

        $dayBefore = clone $startDate;
        $dayBefore->addDay(-1);
        $hashrateValues[$dayBefore->format(self::DateFormat)] = 0;

        while ($iterationDate < $endDate) {
            $previousDay = clone $iterationDate;
            $iterationDate->addDay(1);
            $iterationDays = $iterationDate->diffInDays($startDate);
            $dateKey = $iterationDate->format(self::DateFormat);
            $previousDayKey = $previousDay->format(self::DateFormat);

            $previousHashrate = $hashrateValues[$previousDayKey];

            if ($iterationDays < $keepReinvestingDays) {
                $amountIncrease = $profit[$dateKey] * $previousHashrate;
                $amountValues[$dateKey]['btc'] = $amountValues[$previousDayKey]['btc'] + $amountIncrease * $percentageKept;
                $amountValues[$dateKey]['usd'] = $amountValues[$previousDayKey]['usd'] + $amountIncrease * $percentageKept * $prices[$dateKey];

                if ($iterationDays < $contractLength) {
                    $hashrateValues[$dateKey] = $previousHashrate + $amountIncrease * (1.0 - $percentageKept) / $this->getHashPrice($prices[$dateKey]);
                } else {
                    $dayYearBefore = clone $iterationDate;
                    $dayYearBefore->addYear(-1);

                    $hashrateValues[$dateKey] = $previousHashrate + $amountIncrease * (1.0 - $percentageKept) / $this->getHashPrice($prices[$dateKey]) - $hashrateValues[$dayYearBefore->format(self::DateFormat)];
                }
            } else {
                $amountIncrease = $profit[$dateKey] * $previousHashrate;
                $amountValues[$dateKey]['btc'] = $amountValues[$previousDayKey]['btc'] + $amountIncrease;
                $amountValues[$dateKey]['usd'] = $amountValues[$previousDayKey]['usd'] + $amountIncrease * $prices[$dateKey];


                if ($iterationDays < $contractLength) {
                    $hashrateValues[$dateKey] = $previousHashrate;
                } else {
                    $dayYearBefore = clone $iterationDate;
                    $dayYearBefore->addYear(-1);

                    $dayYearBeforeBefore = clone $dayYearBefore;
                    $dayYearBeforeBefore->addDay(-1);

                    $hashrateValues[$dateKey] = $previousHashrate + ($hashrateValues[$dayYearBeforeBefore->format(self::DateFormat)] ?? 0) - ($hashrateValues[$dayYearBefore->format(self::DateFormat)] ?? 0);
                }
            }
        }

        return compact('amountValues', 'hashrateValues', 'investPrice');
    }
}