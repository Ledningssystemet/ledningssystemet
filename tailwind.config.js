import tailwindcssAnimate from 'tailwindcss-animate';

/**
 * Merge these settings into your Laravel project's tailwind config.
 */
const designSystemExtend = {
    fontFamily: {
        heading: ['Quicksand', 'sans-serif'],
        body: ['Inter', 'sans-serif'],
    },
    colors: {
        border: 'hsl(var(--border))',
        input: 'hsl(var(--input))',
        ring: 'hsl(var(--ring))',
        background: 'hsl(var(--background))',
        foreground: 'hsl(var(--foreground))',
        primary: {
            DEFAULT: 'hsl(var(--primary))',
            foreground: 'hsl(var(--primary-foreground))',
        },
        secondary: {
            DEFAULT: 'hsl(var(--secondary))',
            foreground: 'hsl(var(--secondary-foreground))',
        },
        destructive: {
            DEFAULT: 'hsl(var(--destructive))',
            foreground: 'hsl(var(--destructive-foreground))',
        },
        muted: {
            DEFAULT: 'hsl(var(--muted))',
            foreground: 'hsl(var(--muted-foreground))',
        },
        accent: {
            DEFAULT: 'hsl(var(--accent))',
            foreground: 'hsl(var(--accent-foreground))',
        },
        success: {
            DEFAULT: 'hsl(var(--success))',
            foreground: 'hsl(var(--success-foreground))',
        },
        warning: {
            DEFAULT: 'hsl(var(--warning))',
            foreground: 'hsl(var(--warning-foreground))',
        },
        popover: {
            DEFAULT: 'hsl(var(--popover))',
            foreground: 'hsl(var(--popover-foreground))',
        },
        card: {
            DEFAULT: 'hsl(var(--card))',
            foreground: 'hsl(var(--card-foreground))',
        },
    },
    borderRadius: {
        lg: 'var(--radius)',
        md: 'calc(var(--radius) - 2px)',
        sm: 'calc(var(--radius) - 4px)',
    },
    boxShadow: {
        soft: '0 1px 2px 0 hsl(var(--foreground) / 0.06), 0 1px 3px 1px hsl(var(--foreground) / 0.04)',
        card: '0 8px 24px -10px hsl(var(--foreground) / 0.18)',
        focus: '0 0 0 3px hsl(var(--ring) / 0.35)',
    },
    keyframes: {
        'fade-in': {
            from: { opacity: '0' },
            to: { opacity: '1' },
        },
        'slide-up': {
            from: { opacity: '0', transform: 'translateY(8px)' },
            to: { opacity: '1', transform: 'translateY(0)' },
        },
        'pulse-soft': {
            '0%, 100%': { opacity: '1' },
            '50%': { opacity: '.7' },
        },
    },
    animation: {
        'fade-in': 'fade-in 220ms ease-out',
        'slide-up': 'slide-up 280ms ease-out',
        'pulse-soft': 'pulse-soft 2.2s ease-in-out infinite',
    },
};

/** @type {import('tailwindcss').Config} */
const config = {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.jsx',
        './resources/**/*.ts',
        './resources/**/*.tsx',
    ],
    theme: {
        extend: designSystemExtend,
    },
    plugins: [
        // Uses installed package: tailwindcss-animate
        tailwindcssAnimate,
    ],
};

export default config;
