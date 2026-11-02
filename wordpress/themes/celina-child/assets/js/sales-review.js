'use strict';

/* global HTMLButtonElement, HTMLElement */

document.addEventListener( 'DOMContentLoaded', () => {
  const button = document.querySelector( '.celina-back-to-top' );
  if ( ! ( button instanceof HTMLButtonElement ) ) {
    return;
  }
  const update = () => {
    button.hidden = window.scrollY < 600;
  };
  window.addEventListener( 'scroll', update, { passive: true } );
  update();
  button.addEventListener( 'click', () => {
    const logo = document.querySelector(
      '.celina-header .custom-logo-link, .celina-header .celina-logo, .celina-block-header-logo a'
    );
    if ( logo instanceof HTMLElement ) {
      logo.focus( { preventScroll: true } );
    }
    window.scrollTo( {
      top: 0,
      behavior: window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches
        ? 'instant'
        : 'smooth',
    } );
  } );
} );
