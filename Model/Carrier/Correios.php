<?php

namespace Tezus\Correios\Model\Carrier;

use Psr\Log\LoggerInterface;
use Tezus\Correios\Helper\Data as CorreiosHelper;
use Magento\Shipping\Model\Rate\Result;
use Magento\Catalog\Model\ProductRepository;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;

class Correios extends AbstractCarrier implements CarrierInterface {

    /** @var string */
    private const URL = "http://ws.correios.com.br/calculador/CalcPrecoPrazo.aspx?StrRetorno=xml&";

    /** @var string */
    protected $_code = 'correios';

    /** @var bool */
    protected $_isFixed = false;

    /** @var ResultFactory */
    private $rateResultFactory;

    /** @var MethodFactory */
    private $rateMethodFactory;

    /** @var ProductRepository */
    private $productRepository;

    /** @var CorreiosHelper */
    private $correiosHelper;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        ResultFactory $rateResultFactory,
        MethodFactory $rateMethodFactory,
        ProductRepository $productRepository,
        CorreiosHelper $correiosHelper,
        array $data = []
    ) {
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
        $this->productRepository = $productRepository;
        $this->correiosHelper = $correiosHelper;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * Correios Shipping Rates Collector
     *
     * @param RateRequest $request
     * @return Result|bool
     */
    public function collectRates(RateRequest $request) {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        try {
            /** @var Result */
            $result = $this->rateResultFactory->create();

            $methods = explode(",", $this->getConfigData('shipment_type'));
            foreach ($methods as $keys => $send) {
                if ($request->getAllItems()) {
                    $total_peso = 0;
                    $total_cm_cubico = 0;

                    $attributes = $this->correiosHelper->getAttributes();
                    foreach ($request->getAllItems() as $key => $item) {
                        $product = $this->productRepository->getById($item->getProductId());

                        $productData['height'] = $product->getData()[$attributes['height']] ?? $this->getConfigData('default_height');
                        $productData['width'] = $product->getData()[$attributes['width']] ?? $this->getConfigData('default_width');
                        $productData['length'] = $product->getData()[$attributes['length']] ?? $this->getConfigData('default_length');

                        if ($this->correiosHelper->validateProduct($productData)) {
                            $row_peso = $product->getWeight() * $item->getQty();
                            $row_cm = ($productData['height'] * $productData['width'] * $productData['length']) * $item->getQty();

                            $total_peso += $row_peso;
                            $total_cm_cubico += $row_cm;
                        }
                    }
                    $raiz_cubica = round(pow($total_cm_cubico, 1 / 3), 2);
                }

                if ($this->getConfigFlag('contract_number')) {
                    $data[$keys]['nCdEmpresa'] = $this->getConfigFlag('contract_number');
                }
                if ($this->getConfigFlag('contract_password')) {
                    $data[$keys]['sDsSenha'] = $this->getConfigFlag('contract_password');
                }

                $finalPrice = 0;
                $order = [];

                $limiteCorreios = $this->getConfigData('max_weight');
                $qtyOrders = intval(ceil($total_peso / $limiteCorreios));

                if ($total_peso > $limiteCorreios) {
                    $intOrders = intval(floor($total_peso / $limiteCorreios));

                    for ($i = 0; $i < $qtyOrders; $i++) {
                        if ($i < $intOrders) {
                            $order[$i] = $limiteCorreios;
                        } else {
                            $order[$i] = $total_peso % $limiteCorreios;
                        }
                    }
                } else {
                    $order[0] = $total_peso;
                }

                for ($i = 0; $i < sizeof($order); $i++) {

                    $data[$keys]['nCdServico'] = $send;
                    $data[$keys]['nVlPeso'] = $order[$i] < 0.3 ? 0.3 : $order[$i];
                    $data[$keys]['nCdFormato'] = '1';
                    $data[$keys]['nVlComprimento'] = $raiz_cubica < 16 ? 16 : $raiz_cubica;
                    $data[$keys]['nVlAltura'] = $raiz_cubica < 2 ? 2 : $raiz_cubica;
                    $data[$keys]['nVlLargura'] = $raiz_cubica < 11 ? 11 : $raiz_cubica;
                    $data[$keys]['nVlDiametro'] = hypot($data[$keys]['nVlComprimento'], $data[$keys]['nVlLargura']);
                    $data[$keys]['sCdMaoPropria'] = $this->getConfigData('own_hands')  === '1' ? "S" : "N";
                    $data[$keys]['sCepDestino'] = $request->getDestPostcode();
                    $data[$keys]['sCepOrigem'] = $this->correiosHelper->getOriginCep();
                    $data[$keys]['nVlValorDeclarado'] = $request->getBaseCurrency()->convert(
                        $request->getPackageValue(),
                        $request->getPackageCurrency()
                    );
                    $data[$keys]['sCdAvisoRecebimento'] = $this->getConfigData('acknowledgment_of_receipt') === '1' ? "S" : "N";

                    $response[$i] = $this->requestCorreios(self::URL . http_build_query($data[$keys]));

                    $dom = new \DOMDocument('1.0', 'ISO-8859-1');
                    $dom->loadXml($response[$i]);

                    if ($dom->getElementsByTagName('MsgErro')->item(0)->nodeValue !== "") {
                        throw new \Exception($dom->getElementsByTagName('MsgErro')->item(0)->nodeValue, 1);
                    }
                    $price[$i] = $dom->getElementsByTagName('Valor')->item(0)->nodeValue;

                    $finalPrice += str_replace(",", ".", $price[$i]);
                }

                if ($request->getFreeShipping()) {
                    $finalPrice = 0.00;
                }

                $prazo = (int)$dom->getElementsByTagName('PrazoEntrega')->item(0)->nodeValue + (int)$this->getConfigData('increment_days_in_delivery_time');
                $codigo = $dom->getElementsByTagName('Codigo')->item(0)->nodeValue;
                /** @var Method $method */
                $method = $this->rateMethodFactory->create();

                $method->setCarrier($this->_code);
                $method->setCarrierTitle($this->getConfigData('title'));

                if ($this->getConfigData('display_delivery_time')) {
                    $mensagem = $this->correiosHelper->getMethodName($send) . " - Em média $prazo dia(s)";
                } else {
                    $mensagem = $this->correiosHelper->getMethodName($send);
                }

                $method->setMethod($codigo);
                $method->setMethodTitle($mensagem);

                $shippingCost = str_replace(",", ".", $finalPrice) + (float)$this->getConfigData('handling_fee');

                $method->setPrice($shippingCost);
                $method->setCost($shippingCost);

                $result->append($method);
            }
        } catch (\Exception $e) {
            if ($this->getConfigData('showmethod')) {
                $result = $this->_rateErrorFactory->create();
                $result->setCarrier($this->_code)
                    ->setCarrierTitle($this->getConfigData('name') . " - " . $this->getConfigData('title'))
                    ->setErrorMessage(($e->getMessage()));
            } else {
                return false;
            }
        }
        return $result;
    }

    function isTrackingAvailable() {
        return true;
    }

    function getAllowedMethods() {
        return [$this->_code => $this->getConfigData('name')];
    }

    private function requestCorreios($url) {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
        ));

        $content = curl_exec($curl);

        curl_close($curl);

        return $content;
    }
}
