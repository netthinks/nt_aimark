import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const FIXTURE = '/Tests/Acceptance/fixtures/labelled-page.html';

test.beforeEach(async ({ page }) => {
  await page.goto(FIXTURE);
});

test('the page with labelled images has no WCAG 2.1 AA violations', async ({ page }) => {
  const results = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .analyze();

  expect(results.violations).toEqual([]);
});

test('every badge carries a text alternative', async ({ page }) => {
  const badges = page.locator('.nt-aimark__badge');
  const count = await badges.count();
  expect(count).toBeGreaterThan(0);

  for (let i = 0; i < count; i++) {
    const badge = badges.nth(i);
    // Either an icon announced via role="img" + aria-label, or visible text.
    // A badge that is neither would be a disclosure nobody can perceive.
    // Three ways a badge can be announced: the icon as role="img" with a
    // label, the badge itself carrying the label (it is a button when the
    // detail panel is on, and the icon is then aria-hidden), or visible text.
    const labelled = await badge.locator('[role="img"][aria-label]').count();
    const ownLabel = (await badge.getAttribute('aria-label')) ?? '';
    const text = (await badge.innerText()).trim();

    expect(labelled > 0 || ownLabel.trim().length > 0 || text.length > 0).toBe(true);
  }
});

test('the detail panel starts collapsed and is announced as such', async ({ page }) => {
  const toggles = page.locator('.nt-aimark__badge[aria-controls]');
  const count = await toggles.count();
  expect(count).toBeGreaterThan(0);

  for (let i = 0; i < count; i++) {
    await expect(toggles.nth(i)).toHaveAttribute('aria-expanded', 'false');
    const panelId = await toggles.nth(i).getAttribute('aria-controls');
    await expect(page.locator(`#${panelId}`)).toBeHidden();
  }
});

test('the detail panel is operable by keyboard alone', async ({ page }) => {
  const toggle = page.locator('.nt-aimark__badge[aria-controls]').first();
  const panelId = await toggle.getAttribute('aria-controls');
  const panel = page.locator(`#${panelId}`);

  await toggle.focus();
  await expect(toggle).toBeFocused();

  await page.keyboard.press('Enter');
  await expect(toggle).toHaveAttribute('aria-expanded', 'true');
  await expect(panel).toBeVisible();
  // Focus has to stay put: moving it into the panel would make the disclosure
  // harder to operate, not easier.
  await expect(toggle).toBeFocused();

  await page.keyboard.press('Space');
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');
  await expect(panel).toBeHidden();
  await expect(toggle).toBeFocused();
});

test('each aria-controls target exists exactly once', async ({ page }) => {
  const ids = await page.locator('.nt-aimark__badge[aria-controls]').evaluateAll((nodes) =>
    nodes.map((node) => node.getAttribute('aria-controls')),
  );

  expect(new Set(ids).size).toBe(ids.length);

  for (const id of ids) {
    await expect(page.locator(`#${id}`)).toHaveCount(1);
  }
});

test('the badge does not move the image', async ({ page }) => {
  const figure = page.locator('figure.nt-aimark').first();
  const image = figure.locator('.placeholder').first();

  const before = await image.boundingBox();
  await figure.locator('.nt-aimark__badge[aria-controls]').click();
  const after = await image.boundingBox();

  expect(after.x).toBe(before.x);
  expect(after.y).toBe(before.y);
  expect(after.width).toBe(before.width);
  expect(after.height).toBe(before.height);
});

test('the disclosure is not conveyed by position or colour alone', async ({ page }) => {
  // Each badge must resolve to words, whether through the icon's label or
  // through visible text.
  const descriptions = await page.locator('.nt-aimark__badge').evaluateAll((nodes) =>
    nodes.map((node) => {
      const labelled = node.querySelector('[role="img"][aria-label]');

      if (labelled) {
        return labelled.getAttribute('aria-label');
      }

      return (node.getAttribute('aria-label') || node.innerText).trim();
    }),
  );

  expect(descriptions.length).toBeGreaterThan(0);
  for (const description of descriptions) {
    expect(description.trim().length).toBeGreaterThan(0);
  }
});
