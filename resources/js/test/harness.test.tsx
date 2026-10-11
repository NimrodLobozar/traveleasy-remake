// Proof that the component-test harness works (TE-005 AC1–AC3). Real component tests live next
// to their component as `*.test.tsx`.
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Button } from 'react-aria-components';
import { describe, expect, it, vi } from 'vite-plus/test';
import { cn } from '@/lib/utils';

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
});
