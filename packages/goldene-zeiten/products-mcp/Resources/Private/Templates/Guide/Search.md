{escapingEnabled=false}# {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:search.title', languageKey: locale, arguments: {0: shopName})}

{f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:search.covers', languageKey: locale)}

- {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:search.field.title', languageKey: locale)}
- {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:search.field.subtitle', languageKey: locale)}
- {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:search.field.itemNumber', languageKey: locale)}
- {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:search.field.description', languageKey: locale)}
- {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:search.field.ean', languageKey: locale)}

## {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:search.filters', languageKey: locale)}

- {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:search.filter.category', languageKey: locale)}
- {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:search.filter.rootCategory', languageKey: locale)}
- {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:search.filter.manufacturer', languageKey: locale)}
<f:if condition="{hasSearch}">
## {f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:search.paging', languageKey: locale)}

{f:translate(key: 'LLL:EXT:products_mcp/Resources/Private/Language/locallang_guide.xlf:search.paging.text', languageKey: locale, arguments: {0: resultsPerPage})}
</f:if>