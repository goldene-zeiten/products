{escapingEnabled=false}# {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:ordering.title', languageKey: locale, arguments: {0: shopName})}

1. {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:ordering.step.product', languageKey: locale)}
2. {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:ordering.step.variant', languageKey: locale)}
3. {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:ordering.step.quantity', languageKey: locale)}
4. {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:ordering.step.basket', languageKey: locale)}
<f:if condition="{basketUrl}">
{f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:ordering.basketUrl', languageKey: locale)}: {basketUrl}
</f:if><f:if condition="{hasPersonalization}">
## {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:ordering.personalization', languageKey: locale)}

{f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:ordering.personalization.text', languageKey: locale)}
</f:if><f:if condition="{hasVouchers}">
## {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:ordering.voucher', languageKey: locale)}

{f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:ordering.voucher.text', languageKey: locale)}
</f:if>