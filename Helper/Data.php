<?php

namespace Tezus\Correios\Helper;

use Magento\Framework\App\State;
use Magento\Checkout\Model\Session;
use Magento\Store\Model\ScopeInterface;
use Magento\Backend\Model\Session\Quote;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\App\Helper\AbstractHelper;
use Tezus\Correios\Logger\Logger as CorreiosLogger;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Data extends AbstractHelper {
    
    /** @var ScopeConfigInterface*/
    protected $scopeConfig;
    
    /** @var Session */
    protected $session;

    /** @var ProductRepository */
    protected $productRepository;

    /** @var State */
    protected $appState;

    /** @var Quote */
    protected $backendSessionQuote;

    /** @var CorreiosLogger */
    private $correiosLogger;


    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Quote $backendSessionQuote,
        ProductRepository $productRepository,
        State $appState,
        Session $session,
        CorreiosLogger $_correiosLogger
    ) {
        $this->appState = $appState;
        $this->session = $session;
        $this->productRepository = $productRepository;
        $this->scopeConfig = $scopeConfig;
        $this->backendSessionQuote = $backendSessionQuote;
        $this->correiosLogger = $_correiosLogger;
    }

    function getConfig($path) {
        $storeScope = ScopeInterface::SCOPE_STORE;
        return $this->scopeConfig->getValue($path, $storeScope);
    }

    function logMessage($message) {
        $this->correiosLogger->info($message);
    }

    function getOriginCep() {
        return $this->getConfig('shipping/origin/postcode');
    }

    function formatZip($zipcode) {
        $new = trim($zipcode);
        $new = preg_replace('/[^0-9\s]/', '', $new);
        if (!preg_match("/^[0-9]{7,8}$/", $new)) {
            return false;
        } elseif (preg_match("/^[0-9]{7}$/", $new)) {
            $new = "0" . $new;
        }
        return $new;
    }

    function validateProduct($_product) {
        $rightHeight = [1, 100];
        $rightWidth = [10, 100];
        $rightLength = [15, 100];

        $height = $_product['height'];
        $width = $_product['width'];
        $length = $_product['length'];

        if (!$length || !$width || !$height) {
            throw new \Exception("Dimensões de um ou mais produtos não foram preenchidos!", 1);
        }
        if ($height < $rightHeight[0] || $height > $rightHeight[1]) {
            throw new \Exception("Altura de um ou mais produtos está fora do permitido.", 1);
        }
        if ($width < $rightWidth[0] || $width > $rightWidth[1]) {
            throw new \Exception("Largura de um ou mais produtos está fora do permitido.", 1);
        }
        if ($length < $rightLength[0] || $length > $rightLength[1]) {
            throw new \Exception("Comprimento de um ou mais produtos está fora do permitido.", 1);
        }

        return true;
    }

    function getMethodName($method) {
        switch ($method) {
            case 4014: {
                    return "SEDEX";
                }
            case 4510: {
                    return "PAC";
                }
            case 4782: {
                    return "SEDEX 12";
                }
            case 4790: {
                    return "SEDEX 10";
                }
            case 4804: {
                    return "SEDEX Hoje";
                }
        }
    }

    function isActive() {
        return $this->getconfig('carriers/correios/active'); 
    }

    function getTitulo() {
        return $this->getconfig('carriers/correios/title'); 
    }

    function getNomeMetodo() {
        return $this->getconfig('carriers/correios/name'); 
    }

    function getTiposEnvio() {
        return $this->getconfig('carriers/correios/shipment_type'); 
    }

    function getContractNumber() {
        return $this->getconfig('carriers/correios/contract_number'); 
    }

    function getContractPassword() {
        return $this->getconfig('carriers/correios/contract_password'); 
    }

    function entregaPropriasMaos() {
        return $this->getconfig('carriers/correios/own_hands'); 
    }

    function avisoRecebimento() {
        return $this->getconfig('carriers/correios/acknowledgment_of_receipt'); 
    }

    function getDefaultWidth() {
        return $this->getconfig('carriers/correios/default_width'); 
    }

    function getDefaultHeight() {
        return $this->getconfig('carriers/correios/default_height'); 
    }

    function getDefaultLength() {
        return $this->getconfig('carriers/correios/default_length'); 
    }

    function getMaxWidth() {
        return $this->getconfig('carriers/correios/max_width'); 
    }

    function getMaxHeight() {
        return $this->getconfig('carriers/correios/max_height'); 
    }

    function getMaxLength() {
        return $this->getconfig('carriers/correios/max_length'); 
    }

    function getMaximumWeight() {
        return $this->getconfig('carriers/correios/max_weight');
    }

    function getHandlingFee() {
        return $this->getconfig('carriers/correios/handling_fee'); 
    }

    function displayDeliveryTime() {
        return $this->getconfig('carriers/correios/display_delivery_time'); 
    }

    function getIncrementDays() {
        return $this->getconfig('carriers/correios/increment_days_in_delivery_time'); 
    }

    function getHeightAttribute() {
        return $this->getconfig('carriers/correios/height_attribute');
    }

    function getLengthAttribute() {
        return $this->getconfig('carriers/correios/length_attribute');
    }

    function getWidthAttribute() {
        return $this->getconfig('carriers/correios/width_attribute');
    }


    function getAttributes() {
        $data = [];

        $data['height'] = $this->getHeightAttribute();
        $data['length'] = $this->getLengthAttribute();
        $data['width'] = $this->getWidthAttribute();

        return $data;
    }
}
