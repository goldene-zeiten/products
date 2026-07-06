export type NodeType = 'category' | 'product' | 'article';

export interface TreeNode {
  identifier: string;
  parentIdentifier: string;
  type: NodeType;
  uid: number;
  title: string;
  hidden: boolean;
  icon: string;
  hasChildren: boolean;
  itemNumber?: string;
}

export interface SearchMatch extends TreeNode {
  ancestors: string[];
}

export type DropPosition = 'before' | 'after' | 'into';

/**
 * Custom dataTransfer types identifying a drag originating from one of the
 * toolbar's "new node" drag handles, mirroring PageTree's own newTreenode
 * pattern (see @typo3/backend/enum/data-transfer-types.js) so drop handlers
 * can tell "create a new node here" apart from "move this existing node here".
 */
export const NEW_CATEGORY_DATA_TRANSFER_TYPE = 'application/x-goldene-zeiten-new-category';
export const NEW_PRODUCT_DATA_TRANSFER_TYPE = 'application/x-goldene-zeiten-new-product';

/**
 * Node type a "new node" drag/drop creates, and the type of tree node it may
 * be dropped onto (`null` meaning empty tree background/root).
 */
export type NewNodeType = 'category' | 'product';
