<?php

declare(strict_types=1);

namespace Storetransform\Company\Model;

use Amasty\CompanyAccount\Model\WebsiteCurrency as AmastyWebsiteCurrency;
use Magento\Directory\Model\Currency;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Directory\Model\PriceCurrency;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class WebsiteCurrency extends AmastyWebsiteCurrency
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    
    /**
     * @var array|null
     */
    private $baseCurrencies;

    /**
     * @var CurrencyFactory
     */
    private $currencyFactory;

    /**
     * @var Currency[]
     */
    private $currencies;

    /**
     * @var PriceCurrency
     */
    private $priceCurrency;
    private $logger;

    public function __construct(
        StoreManagerInterface $storeManager,
        CurrencyFactory $currencyFactory,
        PriceCurrency $priceCurrency,
        LoggerInterface $logger
    ) {
        $this->storeManager = $storeManager;
        $this->currencyFactory = $currencyFactory;
        $this->priceCurrency = $priceCurrency;
        $this->logger = $logger;
    }

    public function getAllowedCreditCurrencies(): array
    {
        // Custom logic for allowed credit currencies
        if ($this->baseCurrencies === null) {
            $this->baseCurrencies = [];

            foreach ($this->storeManager->getStores(true) as $store) {
                $availableCurrencies = $store->getAvailableCurrencyCodes();

                foreach ($availableCurrencies as $currency) {
                    $this->baseCurrencies[$currency] = $currency;
                }
            }
        }

        return $this->baseCurrencies;
    }

    public function getCurrencyByCode(?string $currencyCode = null): Currency
    {
        if (isset($this->currencies[$currencyCode])) {
            return $this->currencies[$currencyCode];
        }

        if (!$currencyCode) {
            return $this->storeManager->getStore()->getBaseCurrency();
        }

        $currency = $this->currencyFactory->create();
        $this->currencies[$currencyCode] = $currency->load($currencyCode);

        if (!$currency->getCurrencyCode()) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __("Currency code '%1' is not valid.", $currencyCode)
            );
        }

        return $this->currencies[$currencyCode];
    }

    public function getBaseRate(string $fromCurrencyCode, string $toCurrencyCode): float
    {
        $toCurrency = $this->getCurrencyByCode($toCurrencyCode);
        $fromCurrency = $this->getCurrencyByCode($fromCurrencyCode);

        $rate = $this->priceCurrency->getCurrency(null, $fromCurrency)->getRate($toCurrency);

        if (!$rate) {
            $this->logger->error(sprintf(
                'Missing currency rate for %s -> %s.',
                $fromCurrencyCode,
                $toCurrencyCode
            ));

            throw new \Magento\Framework\Exception\LocalizedException(
                __("Exchange rate for '%1' to '%2' is not defined.", $fromCurrencyCode, $toCurrencyCode)
            );
        }

        return (float) $rate;
    }
}
