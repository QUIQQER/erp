# Manual calculation: document model

This is step 1 of the manual amount entry work described in
[pcsg/buero#551](https://dev.quiqqer.com/pcsg/buero/-/issues/551#note_352699).
It introduces the persisted policy only. Calculation guards, amount editing,
validation and the purchasing endpoints are subsequent steps. Setting the mode
does **not yet disable calculation** or protect values against recalculation.

## Calculation mode

The article list owns the mode for the whole document, including all positions
and document totals. Individual articles do not have a separate mode.

`QUI\ERP\Enums\Accounting\CalculationMode` defines `AUTOMATIC` (`automatic`)
and `MANUAL` (`manual`). `ArticleListUnique::getCalculationMode()` exposes it;
the editable `ArticleList::setCalculationMode()` changes it without calculating
or replacing amounts. Immutable lists expose no mode setter.

The existing `$calculated` properties indicate the validity of the calculation
cache, not the document's mode. Cache invalidation must not change the mode.

The existing article list JSON gains a top-level `calculationMode` string next
to `articles`, `calculations` and `priceFactors`. Missing mode means `automatic`
for older documents. Explicit invalid values are rejected with an ERP exception
(HTTP code 400); they must not silently enable automatic calculation.

```php
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Enums\Accounting\CalculationMode;

$List = new ArticleList($storedArticleList);
$List->setCalculationMode(CalculationMode::MANUAL);
$json = $List->serialize();
$Reloaded = new ArticleList(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
$Unique = $Reloaded->toUniqueList();
// Both lists retain CalculationMode::MANUAL.
```

The mode survives array/JSON serialization, loading, conversion to
`ArticleListUnique` and construction of an editable list from a stored snapshot.
No database column or migration is needed: ERP documents already persist the
complete article list as JSON in their `articles` field. Save endpoints that
currently accept only positions must be extended in later steps.

## Amount fields to reuse

Keep amounts in the existing fields instead of introducing a second, divergent
`manualAmounts` structure. The `calculated` and `calculations` names are legacy
storage names; their contents may eventually be entered manually.

| Location | Fields | Meaning |
| --- | --- | --- |
| Position | `unitPrice`, `quantity`, `vat` | Unit price, quantity and tax rate |
| Position `calculated` | `nettoPriceNotRounded`, `nettoPrice`, `nettoBasisPrice` | Net unit prices, including original precision |
| Position `calculated` | `nettoSubSum`, `nettoSum` | Net amounts before and after discounts |
| Position `calculated` | `price`, `basisPrice`, `sum` | Existing amounts in the stored pricing context (`isNetto`) |
| Position `calculated.vatArray` | `vat`, `sum`, `text` | Tax rate, original tax amount and label for that position |
| List `calculations` | `nettoSubSum`, `nettoSum` | Document net amounts before and after price factors |
| List `calculations` | `subSum`, `grandSubSum`, `sum` | Subtotal in the pricing context, gross amount before grand-total factors, final gross amount |
| List `calculations.vatArray[rate]` | `vat`, `sum`, `text` | Document tax amounts grouped by rate |

`Calculations` can already read the existing stored totals and tax amounts.
Its `CalculationValue::value()` returns the raw number; `get()` applies the
configured precision. Currency formatting and `display_*` values are presentation
only and must never become the source for persisted amounts.

The precision regression fixture retains net 8.3950, VAT 1.5951 and final gross
9.99 independently. No tax rate calculation or two-decimal normalization is
required to store those values. Do not enforce exact unrounded equality in the
model: validation and the handling of supplier rounding belong to later steps.

## Next integration step

Add manual calculation guards to `ArticleList`, `Article` and calculation entry
points. In particular, serialization calls `calc()`, `toUniqueList()` can set the
user on articles, and `setUser()` invalidates calculation caches. Those paths
must eventually respect the persisted policy. This model change alone does not
make manual entry available in the UI or support its complete save/post lifecycle.
