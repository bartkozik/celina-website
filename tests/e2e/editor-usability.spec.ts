import { expect, test, type Page } from '@playwright/test';
import { runWithCleanup } from '../support/run-with-cleanup';
import { blockMenuAction } from '../support/block-actions';
import {
  currentEditorEntity,
  deleteEditorEntity,
  expectDraftSaved,
  logIn,
  prepareEditor,
} from '../support/wordpress-editor';

const collections = [
  [ 'FAQ', 'faq', 'faq-item' ],
  [ 'Capability Cards', 'capability-cards', 'capability-card' ],
  [ 'Industry Cards', 'industry-cards', 'industry-card' ],
  [ 'Achievements Timeline', 'achievements-timeline', 'achievement' ],
  [ 'Facility Cards', 'facility-cards', 'facility-card' ],
  [ 'Resource List', 'resource-list', 'resource-item' ],
] as const;

async function openDraft( page: Page ) {
  await logIn( page );
  await page.goto( '/wp-admin/post-new.php?post_type=page' );
  await prepareEditor( page );
  return currentEditorEntity( page );
}

test( 'each collection visibly offers its matching item and preserves newly edited content', async ( {
  page,
} ) => {
  test.setTimeout( 90_000 );
  const entity = await openDraft( page );
  await runWithCleanup(
    async () => {
      const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
      const inserter = page
        .getByLabel( 'Editor top bar' )
        .getByRole( 'button', {
          name: 'Block Inserter',
          exact: true,
        } );
      for ( const [ title, parentType, childType ] of collections ) {
        await inserter.click();
        await page
          .getByRole( 'searchbox', { name: 'Search', exact: true } )
          .fill( title );
        await page
          .getByRole( 'listbox', { name: 'Blocks', exact: true } )
          .locator( `.editor-block-list-item-celina-${ parentType }` )
          .click();
        await inserter.click();
        const parent = canvas.locator( `[data-type="celina/${ parentType }"]` );
        const items = parent.locator( `[data-type="celina/${ childType }"]` );
        await expect( items.first() ).toBeVisible();
        const before = await items.count();
        const add = parent.locator( '.block-list-appender button' ).last();
        await expect( add ).toHaveAttribute( 'aria-label', /^Add / );
        expect(
          await add.evaluate(
            ( element ) => getComputedStyle( element, '::after' ).content
          )
        ).toContain( 'Add ' );
        await add.click();
        await expect( items ).toHaveCount( before + 1 );
        await items
          .last()
          .locator( '[contenteditable="true"]' )
          .first()
          .fill( `New ${ childType } content` );
        await blockMenuAction( page, items.last(), 'Duplicate' );
        await expect( items ).toHaveCount( before + 2 );
        const duplicatedId = await items.last().getAttribute( 'data-block' );
        await blockMenuAction( page, items.last(), 'Move up' );
        await expect( items.nth( before ) ).toHaveAttribute(
          'data-block',
          duplicatedId!
        );
        for ( let count = before + 2; count > 0; count-- ) {
          await blockMenuAction( page, items.last(), 'Delete' );
          await expect( items ).toHaveCount( count - 1 );
        }
        await add.click();
        await expect( items ).toHaveCount( 1 );
        await items
          .first()
          .locator( '[contenteditable="true"]' )
          .first()
          .fill( `New ${ childType } content` );
      }
      await page.getByRole( 'button', { name: 'Save draft' } ).click();
      await expectDraftSaved( page );
      await page.reload();
      await prepareEditor( page );
      for ( const [ , parentType, childType ] of collections ) {
        await expect(
          canvas.locator( `[data-type="celina/${ parentType }"]` )
        ).toContainText( `New ${ childType } content` );
      }
      await expect(
        canvas.getByText( 'This block contains unexpected or invalid content.' )
      ).toHaveCount( 0 );
    },
    async () => deleteEditorEntity( page, entity )
  );
} );

test( 'article dates distinguish publication from a later update', async ( {
  page,
} ) => {
  const editor = await openDraft( page );
  let articleId = 0;
  await runWithCleanup(
    async () => {
      articleId = await page.evaluate( async ( entity ) => {
        const response = await fetch( `${ entity.apiRoot }wp/v2/posts`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': entity.nonce,
          },
          body: JSON.stringify( {
            title: 'Editorial date regression',
            status: 'publish',
            content: 'Initial article.',
          } ),
        } );
        if ( ! response.ok ) {
          throw new Error( 'Could not create date fixture.' );
        }
        return ( await response.json() ).id as number;
      }, editor );
      await page.goto( `/?p=${ articleId }` );
      await expect( page.locator( '.celina-article-meta time' ) ).toBeVisible();
      await expect( page.locator( '.celina-article-updated' ) ).toHaveCount(
        0
      );
      const updated = await page.evaluate(
        async ( { entity, id } ) => {
          const response = await fetch(
            `${ entity.apiRoot }wp/v2/posts/${ id }`,
            {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': entity.nonce,
              },
              body: JSON.stringify( {
                date: '2020-01-01T12:00:00',
                content: 'Updated article.',
              } ),
            }
          );
          return response.ok;
        },
        { entity: editor, id: articleId }
      );
      expect( updated ).toBe( true );
      await page.reload();
      await expect(
        page.locator( '.celina-article-meta time' )
      ).toHaveAttribute( 'datetime', /^2020-01-01/ );
      await expect( page.locator( '.celina-article-updated' ) ).toContainText(
        'Updated:'
      );
      await expect(
        page.locator( '.celina-article-updated time' )
      ).not.toHaveAttribute( 'datetime', /^2020-01-01/ );
    },
    async () => {
      if ( articleId ) {
        await deleteEditorEntity( page, {
          ...editor,
          id: articleId,
          restBase: 'posts',
        } );
      }
      await deleteEditorEntity( page, editor );
    }
  );
} );

test( 'hero actions and resource files can be changed and cleared without losing the saved draft', async ( {
  page,
} ) => {
  const entity = await openDraft( page );
  await runWithCleanup(
    async () => {
      await page.evaluate( () => {
        type Block = { name: string };
        const { wp } = window as typeof window & {
          wp: {
            blocks: {
              createBlock: (
                name: string,
                attributes?: Record< string, unknown >,
                children?: Block[]
              ) => Block;
            };
            data: {
              dispatch: ( store: string ) => {
                insertBlocks: ( blocks: Block[] ) => void;
              };
            };
          };
        };
        const create = wp.blocks.createBlock;
        wp.data.dispatch( 'core/block-editor' ).insertBlocks( [
          create( 'celina/hero', {
            heading: 'Editable hero',
            backgroundId: 31,
            backgroundUrl:
              '/wp-content/themes/celina-child/assets/images/content/portfolio-image-07.png',
            buttons: [ { label: 'Contact us', url: '/contact/' } ],
          } ),
          create( 'celina/resource-list', {}, [
            create( 'celina/resource-item', {
              title: 'Download kit',
              fileId: 32,
              fileUrl: '/old.pdf',
              linkLabel: 'Download',
            } ),
          ] ),
        ] );
      } );
      const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
      const hero = canvas.locator( '[data-type="celina/hero"]' );
      await expect( hero.locator( '.celina-hero__actions' ) ).toHaveText(
        'Contact us'
      );
      await expect( hero.locator( '.celina-hero__actions a' ) ).toHaveCount(
        0
      );
      await hero.locator( 'h1' ).click();
      const removeBackground = page.getByRole( 'button', {
        name: 'Remove background image',
      } );
      if ( ! ( await removeBackground.isVisible() ) ) {
        await page
          .getByRole( 'button', { name: 'Settings', exact: true } )
          .click();
      }
      await removeBackground.click();
      await expect( hero ).toHaveCSS( 'background-image', 'none' );
      const resource = canvas.locator( '[data-type="celina/resource-item"]' );
      const fileUrl = resource.getByRole( 'textbox', {
        name: 'Resource file URL',
      } );
      await fileUrl.fill( 'javascript:alert(1)' );
      await resource.getByRole( 'button', { name: 'Apply file URL' } ).click();
      await expect( resource.getByRole( 'alert' ) ).toContainText( 'valid' );
      await expect(
        resource.getByText( 'Selected file: /old.pdf' )
      ).toBeVisible();
      await fileUrl.fill( '/media/celina-kit.zip' );
      await resource.getByRole( 'button', { name: 'Apply file URL' } ).click();
      await expect(
        resource.getByText( 'Selected file: /media/celina-kit.zip' )
      ).toBeVisible();
      await page.getByRole( 'button', { name: 'Save draft' } ).click();
      await expectDraftSaved( page );
      await page.reload();
      await prepareEditor( page );
      await expect( hero ).toHaveCSS( 'background-image', 'none' );
      await expect( fileUrl ).toHaveValue( '/media/celina-kit.zip' );
      await resource
        .getByRole( 'button', { name: 'Remove resource file' } )
        .click();
      await expect( fileUrl ).toHaveValue( '' );
      await expect( resource.getByRole( 'status' ) ).toHaveText(
        'Resource file not yet available'
      );
      await expect(
        canvas.getByText( 'This block contains unexpected or invalid content.' )
      ).toHaveCount( 0 );
    },
    async () => deleteEditorEntity( page, entity )
  );
} );
