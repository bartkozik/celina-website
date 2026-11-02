/**
 * Type-only surface for the WordPress component APIs used by CELINA blocks.
 *
 * The runtime import remains @wordpress/components for dependency extraction;
 * this narrow path mapping avoids incompatible transitive package declarations.
 */
import type { ComponentType, ReactNode } from 'react';

interface ButtonProps {
  children?: ReactNode;
  className?: string;
  disabled?: boolean;
  href?: string;
  target?: string;
  rel?: string;
  'aria-label'?: string;
  isDestructive?: boolean;
  onClick?: () => void;
  variant?: 'primary' | 'secondary' | 'tertiary' | 'link';
}

interface PanelBodyProps {
  children?: ReactNode;
  title: string;
}

interface SelectControlProps {
  label: string;
  onChange: ( value: string ) => void;
  options: Array< { label: string; value: string } >;
  value: string;
}

interface RangeControlProps {
  label: string;
  max: number;
  min: number;
  onChange: ( value: number | undefined ) => void;
  value: number;
}

interface TextControlProps {
  help?: ReactNode;
  type?: string;
  label: string;
  onChange: ( value: string ) => void;
  value: string;
}

interface ToggleControlProps {
  checked: boolean;
  help?: string;
  label: string;
  onChange: ( value: boolean ) => void;
}

export const Button: ComponentType< ButtonProps >;
export const PanelBody: ComponentType< PanelBodyProps >;
export const RangeControl: ComponentType< RangeControlProps >;
export const SelectControl: ComponentType< SelectControlProps >;
export const Spinner: ComponentType;
export const TextControl: ComponentType< TextControlProps >;
export const ToggleControl: ComponentType< ToggleControlProps >;
export const Modal: ComponentType< {
  title: string;
  className?: string;
  onRequestClose: () => void;
  onKeyDown?: import('react').KeyboardEventHandler;
  children?: ReactNode;
} >;
export const SlotFillProvider: ComponentType< { children?: ReactNode } >;
export const Popover: { Slot: ComponentType };
