import { useEffect, useRef, useState } from 'react';
import type { ElementType, ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Soft fade-and-rise as a section scrolls into view, once. Skipped for
 * people who ask their system for reduced motion (the content just shows).
 */
export function FadeIn({ children, className, as: Tag = 'div', delay = 0 }: { children: ReactNode; className?: string; as?: ElementType; delay?: number }) {
    const ref = useRef<HTMLElement>(null);
    const [shown, setShown] = useState(false);

    useEffect(() => {
        const el = ref.current;
        if (!el) return;

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
            setShown(true);

            return;
        }

        const io = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setShown(true);
                    io.disconnect();
                }
            },
            { threshold: 0.12, rootMargin: '0px 0px -40px 0px' },
        );
        io.observe(el);

        return () => io.disconnect();
    }, []);

    return (
        <Tag
            ref={ref}
            style={delay ? { transitionDelay: `${delay}ms` } : undefined}
            className={cn('transition-[opacity,transform] duration-700 ease-out', shown ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0', className)}
        >
            {children}
        </Tag>
    );
}
