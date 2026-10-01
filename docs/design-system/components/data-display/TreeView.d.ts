import React from 'react';
export interface TreeNode { id: string; label: string; children?: TreeNode[]; [key: string]: any; }
export interface TreeColumn { key: string; label: string; width?: string | number; render?: (value: any, node: TreeNode) => React.ReactNode; }
export interface TreeViewProps { nodes: TreeNode[]; columns: TreeColumn[]; defaultExpanded?: string[]; className?: string; }
export function TreeView(props: TreeViewProps): JSX.Element;
