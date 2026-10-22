{escapingEnabled=false}# {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:checkout.title', languageKey: locale, arguments: {0: shopName})}

{f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:checkout.steps', languageKey: locale)}

1. {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:checkout.step.address', languageKey: locale)}
2. {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:checkout.step.shipping', languageKey: locale)}
3. {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:checkout.step.payment', languageKey: locale)}
4. {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:checkout.step.review', languageKey: locale)}
5. {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:checkout.step.finalize', languageKey: locale)}
<f:if condition="{pages.checkout}">
{f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:checkout.url', languageKey: locale)}: {pages.checkout}
</f:if>
## {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:checkout.payment', languageKey: locale)}

<f:for each="{paymentMethods}" as="method">- {method}
</f:for>
{f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:checkout.payment.note', languageKey: locale)}

## {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:checkout.shipping', languageKey: locale)}

{f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:checkout.shipping.text', languageKey: locale)}

<f:for each="{shippingCarriers}" as="carrier">- {carrier}
</f:for>
## {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:checkout.countries', languageKey: locale)}

<f:if condition="{countries}"><f:then><f:for each="{countries}" as="country">- {country.name} ({country.code})
</f:for></f:then><f:else>{f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:checkout.countries.all', languageKey: locale)}
</f:else></f:if>
## {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:checkout.legal', languageKey: locale)}

{f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:checkout.legal.text', languageKey: locale, arguments: {0: withdrawalPeriodDays})}

<f:if condition="{pages.terms}">- {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:checkout.legal.terms', languageKey: locale)}: {pages.terms}
</f:if><f:if condition="{pages.privacy}">- {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:checkout.legal.privacy', languageKey: locale)}: {pages.privacy}
</f:if><f:if condition="{pages.withdrawalInstructions}">- {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:checkout.legal.withdrawalInstructions', languageKey: locale)}: {pages.withdrawalInstructions}
</f:if><f:if condition="{pages.withdrawal}">- {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:checkout.legal.withdrawal', languageKey: locale)}: {pages.withdrawal}
</f:if>