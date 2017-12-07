<?php

namespace App\Controller;

use DateTime;
use App\Provider\Data\RawDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

class DataController extends Controller
{
    private $dataProvider;

    public function __construct()
    {
        $this->dataProvider = new RawDataProvider();
    }

    public function prices($startDate = null, $endDate = null)
    {
        return new JsonResponse($this->dataProvider->getPrices(DateTime::createFromFormat(RawDataProvider::DateFormat, $startDate), DateTime::createFromFormat(RawDataProvider::DateFormat, $endDate)));
    }

    public function profit($startDate = null, $endDate = null)
    {
        return new JsonResponse($this->dataProvider->getProfit(DateTime::createFromFormat(RawDataProvider::DateFormat, $startDate), DateTime::createFromFormat(RawDataProvider::DateFormat, $endDate)));
    }

    public function getInvestmentMetrics($startDate = null, $endDate = null)
    {
        $startDate = DateTime::createFromFormat(RawDataProvider::DateFormat, $startDate);
        $endDate = DateTime::createFromFormat(RawDataProvider::DateFormat, $endDate);

        return new JsonResponse([
            'prices' => $this->dataProvider->getPrices($startDate, $endDate),
            'profit' => $this->dataProvider->getProfit($startDate, $endDate),
            'return' => $this->dataProvider->getReturnOnInvestment($startDate, $endDate)
        ]);
    }
}