import React from 'react';
export interface AvatarProps { src?: string; name?: string; size?: 'xs'|'sm'|'md'|'lg'|'xl'|'2xl'; square?: boolean; className?: string; style?: React.CSSProperties; }
export interface AvatarGroupProps { avatars: AvatarProps[]; max?: number; size?: AvatarProps['size']; }
export function Avatar(props: AvatarProps): JSX.Element;
export function AvatarGroup(props: AvatarGroupProps): JSX.Element;
