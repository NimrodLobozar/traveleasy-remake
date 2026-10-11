// Proof that the component-test harness works (TE-005 AC1–AC3). Real component tests live next
// to their component as `*.test.tsx`.
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import { Button } from 'react-aria-components';
import { describe, expect, it, vi } from 'vite-plus/test';
import { cn } from '@/lib/utils';
import { JSDOM_DISABLED_RULES } from './axe';
import { HTML_SNIPPET_MAX_LENGTH, htmlSnippet } from './axe-config';

describe('test harness', () => {
    it('resolves the @/ alias like Vite does', () => {
        expect(cn('p-2', 'p-4')).toBe('p-4');
    });

    it('runs in a DOM environment with the jest-dom matchers', () => {
        render(<p>Hallo</p>);

        expect(screen.getByText('Hallo')).toBeInTheDocument();
    });
});

describe('React Aria Button (AC2)', () => {
    it('can be reached with Tab and pressed with Enter and Space', async () => {
        const user = userEvent.setup();
        const onPress = vi.fn();
        render(<Button onPress={onPress}>Opslaan</Button>);
        const button = screen.getByRole('button', { name: 'Opslaan' });

        await user.tab();
        expect(button).toHaveFocus();

        await user.keyboard('{Enter}');
        await user.keyboard(' ');

        expect(onPress).toHaveBeenCalledTimes(2);
    });
});

describe('axe matcher (AC3)', () => {
    it('passes on accessible markup', async () => {
        const { container } = render(
            <form>
                <label htmlFor="email">E-mailadres</label>
                <input id="email" type="email" autoComplete="email" />
            </form>,
        );

        await expect(container).toHaveNoA11yViolations();
    });

    it('fails on an image without a text alternative', async () => {
        const { container } = render(
            // Deliberate violation: the matcher must report it.
            // oxlint-disable-next-line jsx-a11y/alt-text
            <img src="/fixture.png" width={10} height={10} />,
        );

        await expect(
            expect(container).toHaveNoA11yViolations(),
        ).rejects.toThrow(/image-alt/);
    });

    it('reports target, axe summary and the html snippet per node', async () => {
        const { container } = render(
            // Deliberate violation: the matcher must report it.
            // oxlint-disable-next-line jsx-a11y/alt-text
            <img id="no-alt" src="/fixture.png" width={10} height={10} />,
        );

        const message = await failureOf(
            expect(container).toHaveNoA11yViolations(),
        );

        expect(message).toMatch(/\[critical\] image-alt: /);
        expect(message).toContain(
            'help: https://dequeuniversity.com/rules/axe/',
        );
        expect(message).toContain('- #no-alt');
        expect(message).toMatch(/summary: Fix any of the following:/);
        expect(message).toContain('html: <img id="no-alt"');
    });

    it('cuts html snippets to the maximum length on one line', () => {
        const long = `<div class="a
  b">${'x'.repeat(500)}</div>`;
        const snippet = htmlSnippet(long);

        expect(snippet).toHaveLength(HTML_SNIPPET_MAX_LENGTH);
        expect(snippet.startsWith('<div class="a b">')).toBe(true);
        expect(snippet.endsWith('…')).toBe(true);
        expect(htmlSnippet('<b>kort</b>')).toBe('<b>kort</b>');
    });

    it('fails on a focusable element inside aria-hidden (incomplete in jsdom)', async () => {
        const { container } = render(
            <div aria-hidden="true">
                <button type="button">Verborgen knop</button>
            </div>,
        );

        const message = await failureOf(
            expect(container).toHaveNoA11yViolations(),
        );

        expect(message).toContain('incomplete result(s) that fail in jsdom');
        expect(message).toMatch(/\] aria-hidden-focus: /);
        expect(message).toContain('html: <div aria-hidden="true">');
    });

    it('does not run the layout rules jsdom would pass falsely', async () => {
        // In jsdom target-size passes on any size and color-contrast can't measure; both are off.
        const { container } = render(
            <div>
                <button type="button" style={{ width: 10, height: 10 }}>
                    a
                </button>
                <button type="button" style={{ width: 10, height: 10 }}>
                    b
                </button>
                <p style={{ color: '#eeeeee', background: '#ffffff' }}>
                    Lichtgrijs
                </p>
            </div>,
        );
        const run = vi.spyOn(axe, 'run');

        await expect(container).toHaveNoA11yViolations();

        const options = run.mock.calls[0]?.[1];
        for (const rule of JSDOM_DISABLED_RULES) {
            expect(options?.rules?.[rule]).toEqual({ enabled: false });
        }
        expect(JSDOM_DISABLED_RULES).toEqual(
            expect.arrayContaining([
                'color-contrast',
                'target-size',
                'scrollable-region-focusable',
            ]),
        );
    });

    it('lists other incomplete results in a console note when it passes', async () => {
        const warn = vi
            .spyOn(console, 'warn')
            .mockImplementation(() => undefined);
        const { container } = render(
            // A video without captions: axe can't judge that automatically ("needs review").
            // oxlint-disable-next-line jsx-a11y/media-has-caption
            <video src="/fixture.mp4" />,
        );

        await expect(container).toHaveNoA11yViolations();

        expect(warn).toHaveBeenCalledWith(
            expect.stringMatching(/Needs manual review .*: video-caption/),
        );
    });
});

/** The matcher's failure message, or '' when it passed. */
async function failureOf(check: Promise<void>): Promise<string> {
    try {
        await check;
        return '';
    } catch (error) {
        return error instanceof Error ? error.message : String(error);
    }
}
