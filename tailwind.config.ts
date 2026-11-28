import type { Config } from "tailwindcss";

export default {
  darkMode: ["class"],
  content: ["./resources/js/**/*.{ts,tsx,jsx}"],
  prefix: "",
  theme: {
    container: {
      center: true,
      padding: "2rem",
    },
    extend: {
      colors: {
        brand: {
          navy: {
            DEFAULT: "hsl(var(--brand-navy))",
            deep: "hsl(var(--brand-navy-deep))",
            ink: "hsl(var(--brand-navy-ink))",
          },
          teal: {
            DEFAULT: "hsl(var(--brand-teal))",
            soft: "hsl(var(--brand-teal-soft))",
            dark: "hsl(var(--brand-teal-dark))",
          },
        },
        "on-navy": {
          DEFAULT: "hsl(var(--on-navy))",
          muted: "hsl(var(--on-navy-muted))",
        },
        border: "hsl(var(--border))",
        input: "hsl(var(--input))",
        ring: "hsl(var(--ring))",
        background: "hsl(var(--background))",
        foreground: "hsl(var(--foreground))",
        primary: {
          DEFAULT: "hsl(var(--primary))",
          foreground: "hsl(var(--primary-foreground))",
        },
        secondary: {
          DEFAULT: "hsl(var(--secondary))",
          foreground: "hsl(var(--secondary-foreground))",
        },
        destructive: {
          DEFAULT: "hsl(var(--destructive))",
          foreground: "hsl(var(--destructive-foreground))",
        },
        muted: {
          DEFAULT: "hsl(var(--muted))",
          foreground: "hsl(var(--muted-foreground))",
        },
        accent: {
          DEFAULT: "hsl(var(--accent))",
          foreground: "hsl(var(--accent-foreground))",
        },
        popover: {
          DEFAULT: "hsl(var(--popover))",
          foreground: "hsl(var(--popover-foreground))",
        },
        card: {
          DEFAULT: "hsl(var(--card))",
          foreground: "hsl(var(--card-foreground))",
        },
        surface: {
          DEFAULT: "hsl(var(--surface))",
          hover: "hsl(var(--surface-hover))",
        },
        badge: {
          info: "hsl(var(--badge-info))",
          "info-foreground": "hsl(var(--badge-info-foreground))",
          success: "hsl(var(--badge-success))",
          "success-foreground": "hsl(var(--badge-success-foreground))",
          warning: "hsl(var(--badge-warning))",
          "warning-foreground": "hsl(var(--badge-warning-foreground))",
          neutral: "hsl(var(--badge-neutral))",
          "neutral-foreground": "hsl(var(--badge-neutral-foreground))",
        },
        "detail-label": "hsl(var(--detail-label))",
        "list-active": "hsl(var(--list-active))",
        "list-active-border": "hsl(var(--list-active-border))",
        sidebar: {
          DEFAULT: "hsl(var(--sidebar-background))",
          foreground: "hsl(var(--sidebar-foreground))",
          primary: "hsl(var(--sidebar-primary))",
          "primary-foreground": "hsl(var(--sidebar-primary-foreground))",
          accent: "hsl(var(--sidebar-accent))",
          "accent-foreground": "hsl(var(--sidebar-accent-foreground))",
          border: "hsl(var(--sidebar-border))",
          ring: "hsl(var(--sidebar-ring))",
        },
      },
      borderRadius: {
        lg: "var(--radius)",
        md: "calc(var(--radius) - 2px)",
        sm: "calc(var(--radius) - 4px)",
      },
      backgroundImage: {
        brand: "var(--gradient-brand)",
        teal: "var(--gradient-teal)",
      },
      boxShadow: {
        card: "var(--shadow-card)",
      },
      fontFamily: {
        brand: ['"Instrument Sans Variable"', "system-ui", "-apple-system", '"Segoe UI"', "sans-serif"],
        heading: ['"Quicksand Variable"', "system-ui", "sans-serif"],
      },
      keyframes: {
        "accordion-down": {
          from: {
            height: "0",
          },
          to: {
            height: "var(--radix-accordion-content-height)",
          },
        },
        "accordion-up": {
          from: {
            height: "var(--radix-accordion-content-height)",
          },
          to: {
            height: "0",
          },
        },
      },
      animation: {
        "accordion-down": "accordion-down 0.2s ease-out",
        "accordion-up": "accordion-up 0.2s ease-out",
      },
    },
  },
  plugins: [],
} satisfies Config;
