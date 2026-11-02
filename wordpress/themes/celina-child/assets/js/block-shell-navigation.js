'use strict';

/* Native Details keeps the panel accessible even before this enhancement runs. */
document.addEventListener( 'DOMContentLoaded', () => {
  const media = window.matchMedia( '(max-width: 64rem)' );
  document
    .querySelectorAll( '.celina-block-header .celina-block-menu' )
    .forEach( ( menu ) => {
      const summary = menu.querySelector( ':scope > summary' );
      const panel = menu.querySelector( '.celina-block-primary-panel' );
      if ( ! summary || ! panel ) {
        return;
      }
      const close = () => {
        menu.open = ! media.matches;
      };
      close();
      menu.addEventListener( 'toggle', () => {
        if ( media.matches && menu.open ) {
          panel.querySelector( 'a' )?.focus();
        }
      } );
      document.addEventListener( 'keydown', ( event ) => {
        if ( event.key === 'Escape' && media.matches && menu.open ) {
          menu.open = false;
          summary.focus();
        }
      } );
      if ( media.addEventListener ) {
        media.addEventListener( 'change', close );
      } else {
        media.addListener( close );
      }
    } );
} );
