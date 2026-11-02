'use strict';

/* global HTMLAnchorElement, HTMLButtonElement, HTMLElement */

document.addEventListener( 'DOMContentLoaded', () => {
  const button = document.querySelector( '.celina-nav-toggle' );
  const menu = document.getElementById( 'celina-mobile-nav' );

  if (
    ! ( button instanceof HTMLButtonElement ) ||
    ! ( menu instanceof HTMLElement )
  ) {
    return;
  }

  const media = window.matchMedia( '(max-width: 64rem)' );
  const openLabel =
    button.getAttribute( 'data-open-label' ) || 'Open navigation';
  const closeLabel =
    button.getAttribute( 'data-close-label' ) || 'Close navigation';
  const closeMenu = () => {
    button.setAttribute( 'aria-expanded', 'false' );
    button.setAttribute( 'aria-label', openLabel );
    menu.hidden = true;
  };

  document.documentElement.classList.add( 'celina-navigation-ready' );
  closeMenu();

  button.addEventListener( 'click', () => {
    const open = 'true' !== button.getAttribute( 'aria-expanded' );

    button.setAttribute( 'aria-expanded', String( open ) );
    button.setAttribute( 'aria-label', open ? closeLabel : openLabel );
    menu.hidden = ! open;

    if ( open ) {
      const firstLink = menu.querySelector( 'a' );

      if ( firstLink instanceof HTMLAnchorElement ) {
        firstLink.focus();
      }
    }
  } );

  document.addEventListener( 'keydown', ( event ) => {
    if ( 'Escape' !== event.key || menu.hidden ) {
      return;
    }

    closeMenu();
    button.focus();
  } );

  if ( 'function' === typeof media.addEventListener ) {
    media.addEventListener( 'change', closeMenu );
  } else if ( 'function' === typeof media.addListener ) {
    media.addListener( closeMenu );
  }
} );
