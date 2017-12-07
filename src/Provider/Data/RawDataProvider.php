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
        $values = [];
        $dollarValues = [];
        $profit = $this->getProfit($startDate, Carbon::now());
        $prices = $this->getPrices($startDate, Carbon::now());

        $roiDays = 0;

        $investPrice = $this->getHashPrice($this->getPrices($startDate, $startDate)[$startDate->format(self::DateFormat)]);

        $dollarInvestPrice = $this->getHashPrice(1);

        $earnedPrice = 0;
        $earnedPriceDollars = 0;
        $date = $startDate;

        $date = Carbon::instance($date);
        while ($date < $endDate) {
            $earnedPrice += $profit[$key = $date->format(self::DateFormat)];
            $values[$key] = $earnedPrice;

            $earnedPriceDollars += $profit[$key = $date->format(self::DateFormat)] * $prices[$key = $date->format(self::DateFormat)];
            $dollarValues[$key] = $earnedPriceDollars;
            $date->addDay(1);

            if ($earnedPrice >= $investPrice) {
                $roiDays = $date->diffInDays(Carbon::instance($startDate));
            }
        }

        return compact('values', 'roiDays', 'investPrice', 'dollarValues', 'dollarInvestPrice');
    }
}