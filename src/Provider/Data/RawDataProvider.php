<?php

namespace App\Provider\Data;

use Carbon\Carbon;
use DateTime;

class RawDataProvider
{
    const DateFormat = 'Y-m-d';

    public function getPrices(DateTime $startDate, DateTime $endDate)
    {/*
        $client = new Client();
        $res = $client->request('GET', sprintf('https://api.coindesk.com/v1/bpi/historical/close.json?start=%s&end=%s', $startDate, $endDate));

        $json = json_decode($res->getBody()->getContents(), true);

        return $json['bpi'];*/

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

    public function getProfit(DateTime $startDate, DateTime $endDate)
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

        $prices = $this->getPrices($startDate, $endDate);

        foreach ($values as $date => $value) {
            $values[$date] = $value / $prices[$date];
        }

        return $values;
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