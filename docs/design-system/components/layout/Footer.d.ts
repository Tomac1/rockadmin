import React from 'react';
export interface FooterLink {
  label: string;
  href?: string;
  onClick?: () => void;
  divider?: boolean;
}
export interface FooterProps {
  /** Copyright owner name */
  copyright?: string;
  /** Override year (defaults to current year) */
  year?: number;
  /** Items shown in the Useful Links dropdown */
  usefulLinks?: FooterLink[];
}
/** @startingPoint section="Layout" subtitle="Page footer with copyright and useful links dropdown" viewport="1280x44" */
export function Footer(props: FooterProps): JSX.Element;
