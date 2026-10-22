{escapingEnabled=false}# {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:facts.title', languageKey: locale)}: {shopName}

- {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:facts.baseUrl', languageKey: locale)}: {baseUrl}
- {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:facts.currency', languageKey: locale)}: {currency}
<f:if condition="{grossPrices}"><f:then>- {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:facts.pricing.gross', languageKey: locale)}
</f:then><f:else>- {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:facts.pricing.net', languageKey: locale)}
</f:else></f:if><f:if condition="{vatId}">- {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:facts.vatId', languageKey: locale)}: {vatId}
</f:if><f:if condition="{taxNumber}">- {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:facts.taxNumber', languageKey: locale)}: {taxNumber}
</f:if>
## {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:facts.trader', languageKey: locale)}

<f:if condition="{trader.name}">- {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:facts.trader.name', languageKey: locale)}: {trader.name}
</f:if><f:if condition="{trader.address}">- {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:facts.trader.address', languageKey: locale)}: <f:for each="{trader.addressLines}" as="line" iteration="i">{line}<f:if condition="{i.isLast}"><f:else>, </f:else></f:if></f:for>
</f:if><f:if condition="{trader.email}">- {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:facts.trader.email', languageKey: locale)}: {trader.email}
</f:if><f:if condition="{trader.phone}">- {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:facts.trader.phone', languageKey: locale)}: {trader.phone}
</f:if>