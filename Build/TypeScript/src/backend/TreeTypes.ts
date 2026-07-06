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
