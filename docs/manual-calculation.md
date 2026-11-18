# Manual calculation: document model

This covers steps 1, 2 and the shared editor controls in step 3 of the manual amount entry work described in
[pcsg/buero#551](https://dev.quiqqer.com/pcsg/buero/-/issues/551#note_352699).
The persisted mode now also controls calculation. In manual mode, `calc()` and
`recalculate()` keep the existing values. The editor supports manual entry; connecting the purchasing save and post
endpoints remains a subsequent step.

## Calculation mode

The article list owns the mode for the whole document, including all positions
and document totals. Individual articles do not have a separate mode.

`QUI\ERP\Enums\Accounting\CalculationMode` defines `AUTOMATIC` (`automatic`)
and `MANUAL` (`manual`). `ArticleListUnique::getCalculationMode()` exposes it;
the editable `ArticleList::setCalculationMode()` passes it to its articles.
`Article` uses the same enum. Its mode is inherited at runtime and is not stored
separately. Immutable lists expose no mode setter.

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

## Calculation behavior

Manual mode skips calculation in `ArticleList`, `Article` and the direct
`Calc::calcArticleList()` / `Calc::calcArticlePrice()` entry points. Callbacks
receive the stored values. The mode also applies to loaded, added and replaced
articles, so customer changes and price getters cannot silently recalculate them.
Serialization retains the original totals, including any supplied `vatSum`,
without regenerating amounts or display values.

Adding a price factor in manual mode keeps its supplied amounts and does not
update document totals. Currency assignment keeps its existing behavior; it does
not convert manual amounts. Switching back to automatic invalidates the cache,
allowing the next calculation to run normally.

There are no additional validation rules or exception paths. Inconsistent
amounts remain unchanged; a possible UI hint is deferred to the UI work. The purchasing save/post endpoints still need to accept the editor state.


## Shared editor controls

`articles/CalculationMode` is the mode button in the article list. It shows the current mode and marks the selected menu item.
Switching to manual shows a short tooltip above the button, then hides it after
four seconds. It does not add a permanent row to the article list.

Manual price and total entry uses the existing position cells and inline editor.
The net/gross switch chooses which independently stored amounts are displayed.
A manual tax amount cell uses the same whole-cell editing behavior. `PriceDisplay`
provides the net/gross button group and keeps the selected state visible. Editing a
position or changing its quantity, discount or rate never derives another amount.
Document totals and grouped tax amounts remain independently editable in the
summary. `TaxAmount` groups a rate and amount into one control without deriving
one from the other. Its attached remove button deletes the row; the separate
percent-plus button adds a row. Summary and additional amount inputs share their
alignment. Client serialization preserves the mode and original numeric precision.

Returning to automatic calculation refreshes the list and its positions. Responses
from calculations started before a mode change cannot replace manual values.

The existing summary-container action for opening the price-factor window is
still mouse-only; the new mode buttons and amount buttons support keyboard use.

Verification: manual-mode regression tests and browser checks cover independent
amounts, reopening, late responses, mode display,
whole-cell entry, automatic recalculation, keyboard selection, popup layering and
tooltip placement/expiry. All seven locale catalogs are XML-well-formed. The
published locale XSD currently rejects `context` attributes, including existing
ones in these catalogs; they are required by the current Core locale delivery.


## Shared presentation

The responsive position cards now belong to the ERP article controls, including
field labels, normal and text positions, optional selection checkboxes, inline
editing, expandable descriptions and sorting previews. Purchasing no longer
supplies a separate card layout. The article panels in invoice drafts, orders,
offer drafts, purchasing drafts, sales orders and contracts remove their outer
padding; the shared control owns its toolbar and position spacing.

The calculation button uses an intrinsic translated label sizer, so its width
never changes after opening a hidden editor. Its visible label is Automatic or
Manual; menu entries and the default tooltip identify the calculation setting.
The net/gross control follows the calculation button with a 10px gap, shows a muted translated caption and
truncates long captions on one line. The explanatory tooltip belongs to the whole
control; its hover/focus highlight uses the ERP turquoise palette mix. Both
controls have a white surface and are 34px high, including the add-position button
where the article toolbar provides one.

A shop-price mismatch colors only the caption. It compares the selected display
with `quiqqer/tax` → `shop.isNetto`, independently of customer calculation results.
`quiqqer/erp` → `general.businessType` allows both displays in mixed B2B/B2C shops.
Pending, invalid or unavailable settings do not generate a warning.

The shared summary adopts the compact contract footer layout. It stays hidden
for an empty list and slides in when the first position is added, including text
positions. Reduced motion is respected. No footer shadow is added. Destroying a
summary releases its list subscriptions and prevents late formatter or popup
callbacks from accessing the destroyed DOM.
