import AxeBuilder from '@axe-core/playwright';
import { severeAxeViolations } from '../support/accessibility';
import { expect, test, type Page } from '@playwright/test';
import { runWithCleanup } from '../support/run-with-cleanup';
import {
  currentEditorEntity,
  deleteEditorEntity,
  expectDraftSaved,
  logIn,
  prepareEditor,
} from '../support/wordpress-editor';

const fixturePath = '/';

async function insertBlock(
  page: Page,
  blockName: string,
  blockType: string
): Promise< void > {
  const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
  const blocks = page.getByRole( 'listbox', { name: 'Blocks', exact: true } );

  await page
    .getByRole( 'searchbox', { name: 'Search', exact: true } )
    .fill( blockName );
  await blocks
    .locator( `.editor-block-list-item-${ blockType.replace( '/', '-' ) }` )
    .click();
  await expect(
    canvas.locator( `[data-type="${ blockType }"]` )
  ).toBeVisible();
}

async function articlePullquoteSnapshot( page: Page ) {
  return page.evaluate( () => {
    type EditorBlock = {
      attributes?: Record< string, unknown >;
      innerBlocks?: EditorBlock[];
      name?: string;
    };
    const wordpress = window as typeof window & {
      wp?: {
        blocks?: {
          serialize?: ( blocks: EditorBlock[] ) => string;
        };
        data?: {
          select?: ( store: string ) => unknown;
        };
      };
    };
    const editor = wordpress.wp?.data?.select?.( 'core/block-editor' ) as
      | { getBlocks?: () => EditorBlock[] }
      | undefined;
    const article = editor
      ?.getBlocks?.()
      .find( ( block ) => block.name === 'celina/article-layout' );
    const pullquote = article?.innerBlocks?.find(
      ( block ) => block.name === 'core/pullquote'
    );
    const serialize = wordpress.wp?.blocks?.serialize;

    if ( ! pullquote || ! serialize ) {
      return null;
    }

    const markup = serialize( [ pullquote ] );
    const fragment = document.createElement( 'div' );
    fragment.innerHTML = markup;

    return {
      citation: pullquote.attributes?.citation,
      markup,
      paragraphCount: fragment.querySelectorAll( 'blockquote > p' ).length,
      quote: fragment.querySelector( 'blockquote > p' )?.textContent,
      value: pullquote.attributes?.value,
    };
  } );
}

test( 'three CELINA blocks render accessibly', async ( { page } ) => {
  const response = await page.goto( fixturePath, {
    waitUntil: 'domcontentloaded',
  } );

  expect( response?.ok() ).toBe( true );
  await expect(
    page.getByRole( 'heading', { name: 'Fixture Home' } )
  ).toBeVisible();
  await expect( page.locator( '.celina-faq-item__question' ) ).toHaveText(
    'Fixture question?'
  );
  await expect(
    page.getByRole( 'heading', { name: 'Fixture News', exact: true } )
  ).toBeVisible();

  const results = await new AxeBuilder( { page } ).analyze();
  const severeViolations = severeAxeViolations( results );

  expect( severeViolations ).toEqual( [] );
} );

test( 'FAQ opens from the keyboard', async ( { page } ) => {
  await page.goto( fixturePath, { waitUntil: 'domcontentloaded' } );

  const item = page.locator( '.celina-faq-item' );
  const question = item.locator( 'summary' );
  await question.focus();
  await page.keyboard.press( 'Enter' );

  await expect( item ).toHaveAttribute( 'open', '' );
  await expect( item.getByText( 'Fixture answer.' ) ).toBeVisible();
} );

test( 'unconfigured external embeds make no vendor requests', async ( {
  page,
} ) => {
  const vendorRequests: string[] = [];

  page.on( 'request', ( request ) => {
    const hostname = new URL( request.url() ).hostname;

    if (
      hostname === 'js.hsforms.net' ||
      hostname === 'bamboohr.com' ||
      hostname.endsWith( '.bamboohr.com' ) ||
      hostname === 'recruitingbypaycor.com' ||
      hostname.endsWith( '.recruitingbypaycor.com' )
    ) {
      vendorRequests.push( request.url() );
    }
  } );

  const response = await page.goto( fixturePath, {
    waitUntil: 'networkidle',
  } );

  expect( response?.ok() ).toBe( true );
  await expect(
    page.getByText( 'HubSpot form is not configured in this workbench.' )
  ).toBeVisible();
  await expect(
    page.getByText( 'Paycor jobs are not configured in this workbench.' )
  ).toBeVisible();
  expect( vendorRequests ).toEqual( [] );
} );

test( 'Paycor Jobs inserts, edits, and reloads without vendor requests', async ( {
  page,
} ) => {
  const vendorRequests: string[] = [];
  const clientId = 'portfolio-unconfigured';

  page.on( 'request', ( request ) => {
    const hostname = new URL( request.url() ).hostname;

    if (
      hostname === 'bamboohr.com' ||
      hostname.endsWith( '.bamboohr.com' ) ||
      hostname === 'recruitingbypaycor.com' ||
      hostname.endsWith( '.recruitingbypaycor.com' )
    ) {
      vendorRequests.push( request.url() );
    }
  } );

  await logIn( page );
  await page.goto( '/wp-admin/post-new.php?post_type=page', {
    waitUntil: 'domcontentloaded',
  } );
  await prepareEditor( page );
  let entity = await currentEditorEntity( page );

  await runWithCleanup(
    async () => {
      await page
        .getByLabel( 'Editor top bar' )
        .getByRole( 'button', { name: 'Block Inserter', exact: true } )
        .click();
      const blocks = page.getByRole( 'listbox', {
        name: 'Blocks',
        exact: true,
      } );
      const search = page.getByRole( 'searchbox', {
        name: 'Search',
        exact: true,
      } );

      await search.fill( 'Paycor Jobs' );
      await expect(
        blocks.locator( '.editor-block-list-item-celina-paycor-jobs' )
      ).toBeVisible();
      await search.fill( 'BambooHR' );
      await expect(
        blocks.locator( '.editor-block-list-item-celina-bamboohr-jobs' )
      ).toHaveCount( 0 );

      await insertBlock( page, 'Paycor Jobs', 'celina/paycor-jobs' );

      const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
      const paycor = canvas.locator( '[data-type="celina/paycor-jobs"]' );
      await paycor.click();
      await expect( paycor ).toContainText(
        'Paycor jobs are not configured in this workbench.'
      );

      const clientIdInput = page.getByRole( 'textbox', {
        name: 'Paycor client ID',
      } );
      if ( ! ( await clientIdInput.isVisible() ) ) {
        await page.getByRole( 'button', { name: 'Settings' } ).click();
      }
      await expect( clientIdInput ).toBeVisible();
      await clientIdInput.focus();
      await page.keyboard.type( clientId );
      await expect( clientIdInput ).toHaveValue( clientId );
      await expect( paycor ).toContainText(
        'Paycor jobs are configured. The live list loads only on the frontend.'
      );

      await page.getByRole( 'button', { name: 'Save draft' } ).click();
      await expectDraftSaved( page );
      entity = await currentEditorEntity( page );

      await page.reload( { waitUntil: 'domcontentloaded' } );
      await expect(
        canvas.locator( '[data-type="celina/paycor-jobs"]' )
      ).toContainText( 'Paycor jobs are configured.' );
      await canvas.locator( '[data-type="celina/paycor-jobs"]' ).click();
      await expect( clientIdInput ).toHaveValue( clientId );
      expect( vendorRequests ).toEqual( [] );
    },
    async () => deleteEditorEntity( page, entity )
  );
} );

test( 'Article Layout is discoverable by article and persists valid starter content', async ( {
  page,
} ) => {
  await logIn( page );
  await page.goto( '/wp-admin/post-new.php', {
    waitUntil: 'domcontentloaded',
  } );
  await prepareEditor( page );
  const entity = await currentEditorEntity( page );

  await runWithCleanup(
    async () => {
      const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
      await canvas.getByRole( 'button', { name: 'Add default block' } ).click();
      const emptyParagraph = canvas.getByRole( 'document', {
        name: /empty block; start writing or type forward slash/i,
      } );

      await emptyParagraph.pressSequentially( '/article' );
      const slashResult = canvas.getByRole( 'option', {
        name: 'CELINA Article Layout — REDESIGNED',
        exact: true,
      } );
      await expect( slashResult ).toBeVisible();
      await page.keyboard.press( 'Enter' );
      await expect(
        canvas.locator( '[data-type="celina/article-layout"]' )
      ).toBeVisible();

      const beforeSave = await articlePullquoteSnapshot( page );
      expect( beforeSave ).toMatchObject( {
        citation: 'Quote source',
        paragraphCount: 1,
        quote: 'Pull quote.',
        value: 'Pull quote.',
      } );
      expect( beforeSave?.markup ).not.toContain( '<p><p>' );

      await page.getByRole( 'button', { name: 'Save draft' } ).click();
      await expectDraftSaved( page );
      await page.reload( { waitUntil: 'domcontentloaded' } );

      await expect(
        canvas.locator( '[data-type="celina/article-layout"]' )
      ).toBeVisible();
      await expect(
        canvas.locator( '[data-type="core/pullquote"]' )
      ).toBeVisible();
      await expect(
        canvas.getByText( 'This block contains unexpected or invalid content.' )
      ).toHaveCount( 0 );
      expect( await articlePullquoteSnapshot( page ) ).toMatchObject( {
        citation: 'Quote source',
        paragraphCount: 1,
        quote: 'Pull quote.',
        value: 'Pull quote.',
      } );
    },
    async () => deleteEditorEntity( page, entity )
  );
} );

test( 'reusable HubSpot form presets render safe placeholders', async ( {
  page,
} ) => {
  const hubSpotRequests: string[] = [];

  page.on( 'request', ( request ) => {
    if ( new URL( request.url() ).hostname === 'js.hsforms.net' ) {
      hubSpotRequests.push( request.url() );
    }
  } );

  const response = await page.goto( '/fixture-form-patterns/', {
    waitUntil: 'networkidle',
  } );

  expect( response?.ok() ).toBe( true );
  await expect( page.locator( '.celina-hubspot-form' ) ).toHaveCount( 4 );
  await expect(
    page.getByRole( 'heading', { name: 'Request a Callback' } )
  ).toBeVisible();
  await expect(
    page.getByRole( 'heading', { name: 'Ask the CELINA Team' } )
  ).toBeVisible();
  await expect(
    page.getByRole( 'heading', { name: 'Request a Quote' } )
  ).toBeVisible();
  await expect(
    page.getByRole( 'heading', {
      name: 'Ask a Product or Technical Question',
    } )
  ).toBeVisible();
  await expect(
    page.getByText( 'HubSpot form is not configured in this workbench.' )
  ).toHaveCount( 4 );
  expect( hubSpotRequests ).toEqual( [] );
} );

test( 'a newly published post appears in the dynamic News Grid', async ( {
  page,
} ) => {
  const title = `Browser Published News ${ Date.now() }`;

  await logIn( page );
  await page.goto( '/wp-admin/post-new.php', {
    waitUntil: 'domcontentloaded',
  } );
  await prepareEditor( page );
  let entity = await currentEditorEntity( page );

  await runWithCleanup(
    async () => {
      await page
        .frameLocator( 'iframe[name="editor-canvas"]' )
        .getByRole( 'textbox', { name: 'Add title' } )
        .fill( title );
      await page
        .getByLabel( 'Editor top bar' )
        .getByRole( 'button', { name: 'Publish', exact: true } )
        .click();
      await page
        .getByLabel( 'Editor publish' )
        .getByRole( 'button', { name: 'Publish', exact: true } )
        .click();
      await expect(
        page.getByRole( 'button', { name: 'Change status: Published' } )
      ).toBeVisible();
      entity = await currentEditorEntity( page );

      await page.goto( fixturePath, { waitUntil: 'domcontentloaded' } );
      await expect(
        page.getByRole( 'heading', { name: title } )
      ).toBeVisible();
    },
    async () => deleteEditorEntity( page, entity )
  );
} );

test( 'editor saves and reloads all six public blocks', async ( { page } ) => {
  await logIn( page );
  await page.goto( '/wp-admin/post-new.php?post_type=page', {
    waitUntil: 'domcontentloaded',
  } );
  await prepareEditor( page );
  let entity = await currentEditorEntity( page );

  await runWithCleanup(
    async () => {
      await page
        .getByLabel( 'Editor top bar' )
        .getByRole( 'button', { name: 'Block Inserter', exact: true } )
        .click();

      await insertBlock( page, 'Hero', 'celina/hero' );
      await insertBlock( page, 'CTA Band', 'celina/cta-band' );
      await insertBlock(
        page,
        'Intro / Description Band',
        'celina/intro-band'
      );
      await insertBlock(
        page,
        'Text + Image Split',
        'celina/text-image-split'
      );
      await insertBlock( page, 'FAQ', 'celina/faq' );
      await insertBlock( page, 'News Grid', 'celina/news-grid' );

      const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
      const hero = canvas.locator( '[data-type="celina/hero"]' );
      const cta = canvas.locator( '[data-type="celina/cta-band"]' );
      const intro = canvas.locator( '[data-type="celina/intro-band"]' );
      const textImage = canvas.locator(
        '[data-type="celina/text-image-split"]'
      );
      await hero.locator( 'h1[contenteditable="true"]' ).fill( 'Editor Hero' );
      await cta.locator( 'h2[contenteditable="true"]' ).fill( 'Editor CTA' );
      await intro
        .locator( 'h2[contenteditable="true"]' )
        .fill( 'Editor Intro' );
      await textImage
        .locator( 'h2[contenteditable="true"]' )
        .fill( 'Editor Text and Image' );
      await page.getByRole( 'button', { name: 'Save draft' } ).click();
      await expectDraftSaved( page );
      entity = await currentEditorEntity( page );

      await page.reload( { waitUntil: 'domcontentloaded' } );
      await expect(
        canvas.locator( '[data-type="celina/hero"]' )
      ).toContainText( 'Editor Hero' );
      await expect(
        canvas.locator( '[data-type="celina/cta-band"]' )
      ).toContainText( 'Editor CTA' );
      await expect(
        canvas.locator( '[data-type="celina/intro-band"]' )
      ).toContainText( 'Editor Intro' );
      await expect(
        canvas.locator( '[data-type="celina/text-image-split"]' )
      ).toContainText( 'Editor Text and Image' );
      await expect(
        canvas.locator( '[data-type="celina/faq"]' )
      ).toBeVisible();
      await expect(
        canvas.locator( '[data-type="celina/news-grid"]' )
      ).toBeVisible();
    },
    async () => deleteEditorEntity( page, entity )
  );
} );

test( 'card collections keep controlled children through edit and publish', async ( {
  page,
} ) => {
  await logIn( page );
  await page.goto( '/wp-admin/post-new.php?post_type=page', {
    waitUntil: 'domcontentloaded',
  } );
  await prepareEditor( page );
  let entity = await currentEditorEntity( page );

  await runWithCleanup(
    async () => {
      const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
      await canvas
        .getByRole( 'textbox', { name: 'Add title' } )
        .fill( 'CELINA Card Collections' );
      await page
        .getByLabel( 'Editor top bar' )
        .getByRole( 'button', { name: 'Block Inserter', exact: true } )
        .click();

      await insertBlock( page, 'Capability Cards', 'celina/capability-cards' );
      await insertBlock( page, 'Industry Cards', 'celina/industry-cards' );

      await expect
        .poll( () =>
          page.evaluate( () => {
            type EditorBlock = {
              clientId: string;
              name: string;
            };
            const wordpress = window as typeof window & {
              wp?: {
                data?: {
                  select?: ( store: string ) => {
                    getBlocks?: ( rootClientId?: string ) => EditorBlock[];
                  };
                };
              };
            };
            const selector =
              wordpress.wp?.data?.select?.( 'core/block-editor' );
            const parents = selector?.getBlocks?.() ?? [];

            return parents
              .filter( ( block ) =>
                [ 'celina/capability-cards', 'celina/industry-cards' ].includes(
                  block.name
                )
              )
              .map(
                ( block ) => selector?.getBlocks?.( block.clientId ).length
              );
          } )
        )
        .toEqual( [ 3, 3 ] );

      await page
        .getByLabel( 'Editor top bar' )
        .getByRole( 'button', { name: 'Block Inserter', exact: true } )
        .click();
      const capabilityCollection = canvas.locator(
        '[data-type="celina/capability-cards"]'
      );
      await capabilityCollection
        .locator( '.block-list-appender button' )
        .click();

      await expect
        .poll( () =>
          page.evaluate( () => {
            type EditorBlock = {
              clientId: string;
              name: string;
            };
            const wordpress = window as typeof window & {
              wp?: {
                data?: {
                  select?: ( store: string ) => {
                    getBlocks?: ( rootClientId?: string ) => EditorBlock[];
                  };
                };
              };
            };
            const selector =
              wordpress.wp?.data?.select?.( 'core/block-editor' );
            const capabilityParent = ( selector?.getBlocks?.() ?? [] ).find(
              ( block ) => block.name === 'celina/capability-cards'
            );

            return capabilityParent
              ? selector?.getBlocks?.( capabilityParent.clientId ).length
              : 0;
          } )
        )
        .toBe( 4 );

      await page.evaluate( () => {
        type EditorBlock = {
          attributes: Record< string, unknown >;
          clientId: string;
          name: string;
        };
        type BlockEditorSelector = {
          getBlocks: ( rootClientId?: string ) => EditorBlock[];
        };
        type BlockEditorDispatcher = {
          moveBlockToPosition: (
            clientId: string,
            fromRootClientId: string,
            toRootClientId: string,
            index: number
          ) => void;
          removeBlock: ( clientId: string, selectPrevious?: boolean ) => void;
          updateBlockAttributes: (
            clientId: string,
            attributes: Record< string, unknown >
          ) => void;
        };
        const wordpress = window as typeof window & {
          wp?: {
            data?: {
              dispatch?: ( store: string ) => BlockEditorDispatcher;
              select?: ( store: string ) => BlockEditorSelector;
            };
          };
        };
        const selector = wordpress.wp?.data?.select?.( 'core/block-editor' );
        const dispatcher =
          wordpress.wp?.data?.dispatch?.( 'core/block-editor' );
        const parents = selector?.getBlocks() ?? [];
        const capabilityParent = parents.find(
          ( block ) => block.name === 'celina/capability-cards'
        );
        const industryParent = parents.find(
          ( block ) => block.name === 'celina/industry-cards'
        );

        if ( ! selector || ! dispatcher ) {
          throw new Error( 'WordPress block editor API is unavailable.' );
        }
        if ( ! capabilityParent || ! industryParent ) {
          throw new Error( 'Card collection parents are unavailable.' );
        }

        dispatcher.updateBlockAttributes( capabilityParent.clientId, {
          eyebrow: 'Capabilities',
          heading: 'Mission-ready systems',
          introduction: '<p>Configured for demanding environments.</p>',
          variant: 'dark',
          columns: 3,
        } );
        const capabilityChildren = selector.getBlocks(
          capabilityParent.clientId
        );
        const [ firstCapabilityChild ] = capabilityChildren;
        const addedCapabilityChild = capabilityChildren.at( -1 );
        if ( ! firstCapabilityChild || ! addedCapabilityChild ) {
          throw new Error( 'Capability Cards children are unavailable.' );
        }
        dispatcher.updateBlockAttributes( addedCapabilityChild.clientId, {
          heading: 'Rapid deployment',
          content: '<p>Ready when and where it matters.</p>',
          proof: 'Delivered worldwide',
        } );
        dispatcher.removeBlock( firstCapabilityChild.clientId, false );

        dispatcher.updateBlockAttributes( industryParent.clientId, {
          eyebrow: 'Markets',
          heading: 'Industries we serve',
          introduction: '<p>Purpose-built systems by sector.</p>',
          columns: 3,
        } );
        const industryChildren = selector.getBlocks( industryParent.clientId );
        const [ firstIndustryChild, secondIndustryChild ] = industryChildren;
        if ( ! firstIndustryChild || ! secondIndustryChild ) {
          throw new Error( 'Industry Cards did not seed enough children.' );
        }
        dispatcher.updateBlockAttributes( firstIndustryChild.clientId, {
          title: 'Government',
        } );
        dispatcher.updateBlockAttributes( secondIndustryChild.clientId, {
          title: 'Defense',
          description: '<p>Deployable infrastructure.</p>',
          linkLabel: 'Explore defense',
          linkUrl: '/markets/defense',
        } );
        dispatcher.moveBlockToPosition(
          secondIndustryChild.clientId,
          industryParent.clientId,
          industryParent.clientId,
          0
        );
      } );

      await page.getByRole( 'button', { name: 'Save draft' } ).click();
      await expectDraftSaved( page );
      entity = await currentEditorEntity( page );

      await page.reload( { waitUntil: 'domcontentloaded' } );
      await prepareEditor( page );
      await expect(
        canvas.locator( '[data-type="celina/capability-cards"]' )
      ).toContainText( 'Rapid deployment' );
      await expect(
        canvas.locator( '[data-type="celina/industry-cards"]' )
      ).toContainText( 'Defense' );

      const childState = await page.evaluate( () => {
        type EditorBlock = {
          attributes: Record< string, unknown >;
          clientId: string;
          name: string;
        };
        const wordpress = window as typeof window & {
          wp?: {
            data?: {
              select?: ( store: string ) => {
                getBlocks?: ( rootClientId?: string ) => EditorBlock[];
              };
            };
          };
        };
        const selector = wordpress.wp?.data?.select?.( 'core/block-editor' );
        const parents = selector?.getBlocks?.() ?? [];
        const capabilityParent = parents.find(
          ( block ) => block.name === 'celina/capability-cards'
        );
        const industryParent = parents.find(
          ( block ) => block.name === 'celina/industry-cards'
        );

        return {
          capabilityHeadings: capabilityParent
            ? ( selector?.getBlocks?.( capabilityParent.clientId ) ?? [] ).map(
                ( block ) => block.attributes.heading
              )
            : [],
          industryTitles: industryParent
            ? ( selector?.getBlocks?.( industryParent.clientId ) ?? [] ).map(
                ( block ) => block.attributes.title
              )
            : [],
        };
      } );

      expect( childState.capabilityHeadings ).toHaveLength( 3 );
      expect( childState.capabilityHeadings ).toContain( 'Rapid deployment' );
      expect( childState.industryTitles ).toHaveLength( 3 );
      expect( childState.industryTitles[ 0 ] ).toBe( 'Defense' );

      await page
        .getByLabel( 'Editor top bar' )
        .getByRole( 'button', { name: 'Block Inserter', exact: true } )
        .click();
      const search = page.getByRole( 'searchbox', {
        name: 'Search',
        exact: true,
      } );
      await search.fill( 'Capability Card' );
      await expect(
        page.locator( '.editor-block-list-item-celina-capability-card' )
      ).toHaveCount( 0 );
      await search.fill( 'Industry Card' );
      await expect(
        page.locator( '.editor-block-list-item-celina-industry-card' )
      ).toHaveCount( 0 );
      await page
        .getByLabel( 'Editor top bar' )
        .getByRole( 'button', { name: 'Block Inserter', exact: true } )
        .click();

      await page
        .getByLabel( 'Editor top bar' )
        .getByRole( 'button', { name: 'Publish', exact: true } )
        .click();
      await page
        .getByLabel( 'Editor publish' )
        .getByRole( 'button', { name: 'Publish', exact: true } )
        .click();
      await expect(
        page.getByRole( 'button', { name: 'Change status: Published' } )
      ).toBeVisible();

      await page.goto( `/?page_id=${ entity.id }`, {
        waitUntil: 'domcontentloaded',
      } );
      await expect(
        page.getByRole( 'heading', { name: 'Mission-ready systems' } )
      ).toBeVisible();
      await expect(
        page.getByRole( 'heading', { name: 'Rapid deployment' } )
      ).toBeVisible();
      await expect(
        page.getByRole( 'heading', { name: 'Industries we serve' } )
      ).toBeVisible();
      await expect(
        page.getByRole( 'heading', { name: 'Defense' } )
      ).toBeVisible();

      const results = await new AxeBuilder( { page } ).analyze();
      const severeViolations = severeAxeViolations( results );
      expect( severeViolations ).toEqual( [] );
    },
    async () => deleteEditorEntity( page, entity )
  );
} );

test( 'specialty collections preserve controlled items and honest resource states', async ( {
  page,
} ) => {
  await logIn( page );
  await page.goto( '/wp-admin/post-new.php?post_type=page', {
    waitUntil: 'domcontentloaded',
  } );
  await prepareEditor( page );
  let entity = await currentEditorEntity( page );

  await runWithCleanup(
    async () => {
      const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
      await canvas
        .getByRole( 'textbox', { name: 'Add title' } )
        .fill( 'CELINA Specialty Collections' );
      await page
        .getByLabel( 'Editor top bar' )
        .getByRole( 'button', { name: 'Block Inserter', exact: true } )
        .click();

      await insertBlock(
        page,
        'Achievements Timeline',
        'celina/achievements-timeline'
      );
      await insertBlock( page, 'Facility Cards', 'celina/facility-cards' );
      await insertBlock( page, 'Resource List', 'celina/resource-list' );

      await expect
        .poll( () =>
          page.evaluate( () => {
            type EditorBlock = { clientId: string; name: string };
            const wordpress = window as typeof window & {
              wp?: {
                data?: {
                  select?: ( store: string ) => {
                    getBlocks?: ( rootClientId?: string ) => EditorBlock[];
                  };
                };
              };
            };
            const selector =
              wordpress.wp?.data?.select?.( 'core/block-editor' );
            const parents = selector?.getBlocks?.() ?? [];

            return [
              'celina/achievements-timeline',
              'celina/facility-cards',
              'celina/resource-list',
            ].map( ( name ) => {
              const parent = parents.find( ( block ) => block.name === name );
              return parent
                ? selector?.getBlocks?.( parent.clientId ).length
                : 0;
            } );
          } )
        )
        .toEqual( [ 3, 2, 3 ] );

      await page
        .getByLabel( 'Editor top bar' )
        .getByRole( 'button', { name: 'Block Inserter', exact: true } )
        .click();
      const resourceCollection = canvas.locator(
        '[data-type="celina/resource-list"]'
      );
      await resourceCollection.locator( '.block-list-appender button' ).click();

      await expect
        .poll( () =>
          page.evaluate( () => {
            type EditorBlock = { clientId: string; name: string };
            const wordpress = window as typeof window & {
              wp?: {
                data?: {
                  select?: ( store: string ) => {
                    getBlocks?: ( rootClientId?: string ) => EditorBlock[];
                  };
                };
              };
            };
            const selector =
              wordpress.wp?.data?.select?.( 'core/block-editor' );
            const parent = ( selector?.getBlocks?.() ?? [] ).find(
              ( block ) => block.name === 'celina/resource-list'
            );

            return parent ? selector?.getBlocks?.( parent.clientId ).length : 0;
          } )
        )
        .toBe( 4 );

      await page.evaluate( () => {
        type EditorBlock = {
          attributes: Record< string, unknown >;
          clientId: string;
          name: string;
        };
        type BlockEditorSelector = {
          getBlocks: ( rootClientId?: string ) => EditorBlock[];
        };
        type BlockEditorDispatcher = {
          moveBlockToPosition: (
            clientId: string,
            fromRootClientId: string,
            toRootClientId: string,
            index: number
          ) => void;
          removeBlock: ( clientId: string, selectPrevious?: boolean ) => void;
          updateBlockAttributes: (
            clientId: string,
            attributes: Record< string, unknown >
          ) => void;
        };
        const wordpress = window as typeof window & {
          wp?: {
            data?: {
              dispatch?: ( store: string ) => BlockEditorDispatcher;
              select?: ( store: string ) => BlockEditorSelector;
            };
          };
        };
        const selector = wordpress.wp?.data?.select?.( 'core/block-editor' );
        const dispatcher =
          wordpress.wp?.data?.dispatch?.( 'core/block-editor' );
        const parents = selector?.getBlocks() ?? [];
        const achievements = parents.find(
          ( block ) => block.name === 'celina/achievements-timeline'
        );
        const facilities = parents.find(
          ( block ) => block.name === 'celina/facility-cards'
        );
        const resources = parents.find(
          ( block ) => block.name === 'celina/resource-list'
        );

        if ( ! selector || ! dispatcher ) {
          throw new Error( 'WordPress block editor API is unavailable.' );
        }
        if ( ! achievements || ! facilities || ! resources ) {
          throw new Error( 'Specialty collection parents are unavailable.' );
        }

        dispatcher.updateBlockAttributes( achievements.clientId, {
          eyebrow: 'History',
          heading: 'Selected milestones',
          introduction: '<p>Built through experience.</p>',
          variant: 'timeline',
        } );
        const [ achievement ] = selector.getBlocks( achievements.clientId );
        if ( ! achievement ) {
          throw new Error( 'Achievement child is unavailable.' );
        }
        dispatcher.updateBlockAttributes( achievement.clientId, {
          year: '1991',
          label: 'Established',
          heading: 'CELINA begins',
          content: '<p>A new chapter starts.</p>',
        } );

        dispatcher.updateBlockAttributes( facilities.clientId, {
          eyebrow: 'Locations',
          heading: 'Our facilities',
          introduction: '<p>Where our teams work.</p>',
          columns: 2,
        } );
        const [ facility ] = selector.getBlocks( facilities.clientId );
        if ( ! facility ) {
          throw new Error( 'Facility child is unavailable.' );
        }
        dispatcher.updateBlockAttributes( facility.clientId, {
          name: 'CELINA Ohio',
          address: '<p>123 Main Street</p>',
          phone: '+1 (555) 010-0000',
          email: 'contact@example.invalid',
          label: 'Get directions',
          url: '/contact#ohio',
        } );

        dispatcher.updateBlockAttributes( resources.clientId, {
          eyebrow: 'Downloads',
          heading: 'Corporate resources',
          introduction: '<p>Reference materials.</p>',
          variant: 'featured',
        } );
        const resourceItems = selector.getBlocks( resources.clientId );
        const [ unavailableResource, secondResource ] = resourceItems;
        const addedResource = resourceItems.at( -1 );
        if ( ! unavailableResource || ! secondResource || ! addedResource ) {
          throw new Error( 'Resource children are unavailable.' );
        }
        dispatcher.updateBlockAttributes( unavailableResource.clientId, {
          title: 'Annual report',
          description: '<p>Client-owned file pending.</p>',
          metadata: 'PDF',
          fileId: 0,
          fileUrl: '',
          linkLabel: 'Download report',
        } );
        dispatcher.updateBlockAttributes( secondResource.clientId, {
          title: 'Capabilities brochure',
          metadata: 'PDF · 2 MB',
          fileId: 23,
          fileUrl: '/wp-content/uploads/capabilities.pdf',
          linkLabel: 'Download PDF',
        } );
        dispatcher.removeBlock( addedResource.clientId, false );
        dispatcher.moveBlockToPosition(
          secondResource.clientId,
          resources.clientId,
          resources.clientId,
          0
        );
      } );

      await page.getByRole( 'button', { name: 'Save draft' } ).click();
      await expectDraftSaved( page );
      entity = await currentEditorEntity( page );

      await page.reload( { waitUntil: 'domcontentloaded' } );
      await prepareEditor( page );
      await expect(
        canvas.locator( '[data-type="celina/achievements-timeline"]' )
      ).toContainText( 'CELINA begins' );
      await expect(
        canvas.locator( '[data-type="celina/facility-cards"]' )
      ).toContainText( 'CELINA Ohio' );
      await expect(
        canvas
          .locator( '[data-type="celina/resource-item"]' )
          .filter( { hasText: 'Annual report' } )
          .getByRole( 'status' )
      ).toHaveText( 'Resource file not yet available' );

      const resourceTitles = await page.evaluate( () => {
        type EditorBlock = {
          attributes: Record< string, unknown >;
          clientId: string;
          name: string;
        };
        const wordpress = window as typeof window & {
          wp?: {
            data?: {
              select?: ( store: string ) => {
                getBlocks?: ( rootClientId?: string ) => EditorBlock[];
              };
            };
          };
        };
        const selector = wordpress.wp?.data?.select?.( 'core/block-editor' );
        const resourceParent = ( selector?.getBlocks?.() ?? [] ).find(
          ( block ) => block.name === 'celina/resource-list'
        );

        return resourceParent
          ? ( selector?.getBlocks?.( resourceParent.clientId ) ?? [] ).map(
              ( block ) => block.attributes.title
            )
          : [];
      } );

      expect( resourceTitles ).toHaveLength( 3 );
      expect( resourceTitles[ 0 ] ).toBe( 'Capabilities brochure' );
      expect( resourceTitles ).toContain( 'Annual report' );

      await page
        .getByLabel( 'Editor top bar' )
        .getByRole( 'button', { name: 'Block Inserter', exact: true } )
        .click();
      const search = page.getByRole( 'searchbox', {
        name: 'Search',
        exact: true,
      } );
      for ( const [ name, itemClass ] of [
        [ 'Achievement', 'celina-achievement' ],
        [ 'Facility Card', 'celina-facility-card' ],
        [ 'Resource Item', 'celina-resource-item' ],
      ] as const ) {
        await search.fill( name );
        await expect(
          page.locator( `.editor-block-list-item-${ itemClass }` )
        ).toHaveCount( 0 );
      }
      await page
        .getByLabel( 'Editor top bar' )
        .getByRole( 'button', { name: 'Block Inserter', exact: true } )
        .click();

      await page
        .getByLabel( 'Editor top bar' )
        .getByRole( 'button', { name: 'Publish', exact: true } )
        .click();
      await page
        .getByLabel( 'Editor publish' )
        .getByRole( 'button', { name: 'Publish', exact: true } )
        .click();
      await expect(
        page.getByRole( 'button', { name: 'Change status: Published' } )
      ).toBeVisible();

      await page.goto( `/?page_id=${ entity.id }`, {
        waitUntil: 'domcontentloaded',
      } );
      await expect(
        page.getByRole( 'heading', { name: 'Selected milestones' } )
      ).toBeVisible();
      await expect(
        page.getByRole( 'heading', { name: 'CELINA begins' } )
      ).toBeVisible();
      await expect(
        page.locator( 'ol.celina-achievements-timeline__items' )
      ).toBeVisible();
      await expect(
        page.getByRole( 'heading', { name: 'Our facilities' } )
      ).toBeVisible();
      await expect(
        page.getByRole( 'link', { name: '+1 (555) 010-0000' } )
      ).toHaveAttribute( 'href', 'tel:+15550100000' );
      await expect(
        page.getByRole( 'heading', { name: 'Corporate resources' } )
      ).toBeVisible();
      const unavailable = page
        .locator( 'li.celina-resource-item' )
        .filter( { hasText: 'Annual report' } );
      await expect( unavailable.getByRole( 'status' ) ).toHaveText(
        'Resource file not yet available'
      );
      await expect( unavailable.locator( 'a' ) ).toHaveCount( 0 );

      const results = await new AxeBuilder( { page } ).analyze();
      const severeViolations = severeAxeViolations( results );
      expect( severeViolations ).toEqual( [] );
    },
    async () => deleteEditorEntity( page, entity )
  );
} );
