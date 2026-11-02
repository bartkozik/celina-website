import { expect, type Page } from '@playwright/test';

interface EditorEntity {
  apiRoot: string;
  id: number;
  nonce: string;
  restBase: string;
}

export async function logIn( page: Page ): Promise< void > {
  await page.goto( '/wp-login.php', { waitUntil: 'domcontentloaded' } );
  await page.locator( '#user_login' ).fill( 'admin' );
  await page.locator( '#user_pass' ).fill( 'password' );
  await page.getByRole( 'button', { name: 'Log In' } ).click();
  await expect( page ).toHaveURL( /\/wp-admin\// );
}

export async function prepareEditor( page: Page ): Promise< void > {
  const welcome = page.getByRole( 'dialog', {
    name: 'Welcome to the editor',
  } );

  await page.addLocatorHandler( welcome, async () => {
    await welcome.getByRole( 'button', { name: 'Close' } ).click();
  } );

  await expect(
    page
      .frameLocator( 'iframe[name="editor-canvas"]' )
      .getByRole( 'textbox', { name: 'Add title' } )
  ).toBeVisible();
}

export async function currentEditorEntity(
  page: Page
): Promise< EditorEntity > {
  const entity = await page.evaluate( () => {
    const wordpress = window as typeof window & {
      wp?: {
        data?: {
          select?: ( store: string ) => unknown;
        };
      };
      wpApiSettings?: {
        nonce?: unknown;
        root?: unknown;
      };
    };
    const editor = wordpress.wp?.data?.select?.( 'core/editor' ) as
      | {
          getCurrentPostId?: () => unknown;
          getCurrentPostType?: () => unknown;
        }
      | undefined;
    const core = wordpress.wp?.data?.select?.( 'core' ) as
      | {
          getPostType?: ( postType: string ) => {
            rest_base?: unknown;
          };
        }
      | undefined;
    const id = editor?.getCurrentPostId?.();
    const postType = editor?.getCurrentPostType?.();
    const restBase =
      typeof postType === 'string'
        ? core?.getPostType?.( postType )?.rest_base
        : undefined;
    const apiRoot = wordpress.wpApiSettings?.root;
    const nonce = wordpress.wpApiSettings?.nonce;

    return typeof id === 'number' &&
      typeof restBase === 'string' &&
      typeof apiRoot === 'string' &&
      typeof nonce === 'string'
      ? { apiRoot, id, nonce, restBase }
      : null;
  } );

  if ( ! entity ) {
    throw new Error( 'Could not read the current WordPress editor entity.' );
  }

  return entity;
}

export async function deleteEditorEntity(
  page: Page,
  entity: EditorEntity
): Promise< void > {
  const cleanupPage = await page.context().newPage();

  try {
    await cleanupPage.goto( '/wp-admin/', { waitUntil: 'domcontentloaded' } );
    const result = await cleanupPage.evaluate( async ( editorEntity ) => {
      const response = await fetch(
        `${ editorEntity.apiRoot }wp/v2/${ editorEntity.restBase }/${ editorEntity.id }?force=true`,
        {
          headers: { 'X-WP-Nonce': editorEntity.nonce },
          method: 'DELETE',
        }
      );
      const payload: unknown = await response.json();
      const previous =
        payload && typeof payload === 'object' && 'previous' in payload
          ? payload.previous
          : undefined;
      const previousId =
        previous && typeof previous === 'object' && 'id' in previous
          ? previous.id
          : undefined;

      return {
        deleted:
          payload && typeof payload === 'object' && 'deleted' in payload
            ? payload.deleted
            : false,
        previousId,
        status: response.status,
      };
    }, entity );

    expect( result ).toEqual( {
      deleted: true,
      previousId: entity.id,
      status: 200,
    } );
  } finally {
    await cleanupPage.close();
  }
}

export async function expectDraftSaved( page: Page ): Promise< void > {
  await expect(
    page.getByTestId( 'snackbar' ).filter( { hasText: /draft saved/i } )
  ).toBeVisible();
}
