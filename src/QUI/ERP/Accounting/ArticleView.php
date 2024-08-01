<?php

/**
 * This file contains QUI\ERP\Accounting\ArticleView
 */

namespace QUI\ERP\Accounting;

use QUI;
use QUI\ERP\Accounting\Calc as ErpCalc;

use function implode;

/**
 * Class ArticleView
 *
 * @package QUI\ERP\Accounting
 */
class ArticleView extends QUI\QDOM
{
    /**
     * @var float
     */
    protected float $position = 1;

    /**
     * @var Article
     */
    protected Article $Article;

    /**
     * @var QUI\ERP\Currency\Currency
     */
    protected QUI\ERP\Currency\Currency $Currency;

    /**
     * ArticleView constructor.
     * @param Article $Article
     */
    public function __construct(Article $Article)
    {
        $this->Article = $Article;
        $this->setAttributes($this->Article->toArray());

        if ($this->getAttribute('position')) {
            $this->position = (float)$this->getAttribute('position');
        }
    }

    /**
     * @return string
     */
    public function getQuantityUnit(): string
    {
        return $this->Article->getQuantityUnit();
    }

    /**
     * Set the currency
     *
     * @param QUI\ERP\Currency\Currency $Currency
     */
    public function setCurrency(QUI\ERP\Currency\Currency $Currency): void
    {
        $this->Currency = $Currency;
    }

    /**
     * Set the position
     *
     * @param $position
     */
    public function setPosition($position): void
    {
        $this->position = (float)$position;
    }

    /**
     * @return float
     */
    public function getPosition(): float
    {
        return $this->position;
    }

    /**
     * @return QUI\ERP\Currency\Currency
     */
    public function getCurrency(): QUI\ERP\Currency\Currency
    {
        if ($this->Currency !== null) {
            return $this->Currency;
        }

        return QUI\ERP\Currency\Handler::getDefaultCurrency();
    }

    /**
     * @return array
     */
    public function getCustomFields(): array
    {
        $customFields = [];
        $article = $this->Article->toArray();
        $current = QUI::getLocale()->getCurrent();

        foreach ($article['customFields'] as $field) {
            if (!isset($field['title'])) {
                continue;
            }

            if (isset($field['custom_calc']['valueText'])) {
                if (!\is_string($field['custom_calc']['valueText'])) {
                    if (isset($field['custom_calc']['valueText'][$current])) {
                        $field['custom_calc']['valueText'] = $field['custom_calc']['valueText'][$current];
                    } else {
                        $field['custom_calc']['valueText'] = '';
                    }
                }

                // Add price addition
                $sum = (float)$field['custom_calc']['value'];

                if (
                    !empty($field['custom_calc']['displayDiscounts']) &&
                    (!QUI::isFrontend() || !QUI\ERP\Products\Utils\Package::hidePrice()) &&
                    $sum > 0
                ) {
                    if ($sum >= 0) {
                        $priceAddition = '+';
                    } else {
                        $priceAddition = '-';
                    }

                    switch ((int)$field['custom_calc']['calculation']) {
                        case ErpCalc::CALCULATION_PERCENTAGE:
                            $priceAddition .= $sum . '%';
                            break;

                        default:
                            $priceAddition .= $this->getCurrency()->format($sum);
                            break;
                    }

                    // locale values
                    $field['custom_calc']['valueText'] .= ' (' . $priceAddition . ')';
                }
            }

            $customFields[] = $field;
        }

        return $customFields;
    }

    /**
     * @return bool
     */
    public function displayPrice(): bool
    {
        return $this->Article->displayPrice();
    }

    /**
     * @return string
     */
    public function getPrice(): string
    {
        $Currency = $this->getCurrency();
        $calc = $this->getAttribute('calculated');

        return $Currency->format($calc['price']);
    }

    /**
     * @return string
     */
    public function getImageUrl(): string
    {
        return $this->Article->getImage()->getUrl();
    }

    /**
     * Create the html
     *
     * @return string
     *
     * @throws QUI\Exception
     */
    public function toHTML($template = false): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $Currency = $this->getCurrency();

        $customFields = $this->getCustomFields();
        $article = $this->Article->toArray();
        $calc = $article['calculated'];

        // quantity unit
        if (isset($article['quantityUnit']) && \is_array($article['quantityUnit'])) {
            $article['quantityUnit'] = $article['quantityUnit']['title'];
        }

        $this->setAttributes($article);

        // discount
        $Discount = $this->Article->getDiscount();

        if ($Discount && $Discount->getValue()) {
            $Engine->assign([
                'Discount' => $Discount
            ]);
        }

        $articleData = $this->Article->toArray();
        $cssClasses = ['articles-article-entry'];

        if (!empty($this->Article->getProductSetParentUuid())) {
            $cssClasses[] = 'articles-article--additional';
        } else {
            $cssClasses[] = 'articles-article--real';
        }

        if ($this->getAttribute('odd')) {
            $cssClasses[] = 'articles-article--odd';
        }

        if ($this->getAttribute('even')) {
            $cssClasses[] = 'articles-article--even';
        }

        $Engine->assign([
            'this' => $this,
            'uuid' => $articleData['uuid'],
            'cssClasses' => implode(' ', $cssClasses),
            'productSetParentUuid' => $articleData['productSetParentUuid'],
            'position' => $this->position,
            'unitPrice' => $Currency->format($article['unitPrice']),
            'sum' => $Currency->format($article['sum']),
            'calculated_basisPrice' => $Currency->format($calc['basisPrice']),
            'calculated_price' => $Currency->format($calc['price']),
            'calculated_sum' => $Currency->format($calc['sum']),
            'calculated_nettoSum' => $Currency->format($calc['nettoSum']),
            'customFields' => $customFields,
            'hasAppliedVat' => !empty($articleData['calculated']['vatArray']['vat'])
        ]);

        if ($template && file_exists($template)) {
            return $Engine->fetch($template);
        }

        if ($this->Article instanceof QUI\ERP\Accounting\Articles\Text) {
            return $Engine->fetch(\dirname(__FILE__) . '/ArticleViewText.html');
        }

        return $Engine->fetch(\dirname(__FILE__) . '/ArticleView.html');
    }
}
