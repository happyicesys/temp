<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

const page = usePage();
const name = page.props.name as string;

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-background px-5 py-10 sm:px-6"
    >
        <!-- Ambient background: soft glow + faint grid. Minimal in both themes. -->
        <div class="pointer-events-none absolute inset-0 -z-10">
            <div
                class="absolute top-[-10%] left-1/2 h-[420px] w-[420px] -translate-x-1/2 rounded-full bg-primary/[0.07] blur-3xl dark:bg-primary/[0.10]"
            />
            <div
                class="absolute inset-0 [mask-image:radial-gradient(ellipse_at_center,black_30%,transparent_72%)] opacity-[0.55]"
                style="
                    background-image:
                        linear-gradient(
                            to right,
                            color-mix(
                                    in oklab,
                                    var(--foreground) 7%,
                                    transparent
                                )
                                1px,
                            transparent 1px
                        ),
                        linear-gradient(
                            to bottom,
                            color-mix(
                                    in oklab,
                                    var(--foreground) 7%,
                                    transparent
                                )
                                1px,
                            transparent 1px
                        );
                    background-size: 40px 40px;
                "
            />
        </div>

        <div class="flex w-full max-w-sm flex-col gap-8">
            <!-- Brand -->
            <div class="flex flex-col items-center gap-3 text-center">
                <Link
                    :href="home()"
                    class="group inline-flex items-center gap-2.5 rounded-xl outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    aria-label="Home"
                >
                    <span
                        class="flex size-11 items-center justify-center rounded-xl border border-border bg-card shadow-sm transition-colors group-hover:border-ring"
                    >
                        <AppLogoIcon
                            class="size-6 fill-current text-foreground"
                        />
                    </span>
                    <span class="text-lg font-semibold tracking-tight">{{
                        name
                    }}</span>
                </Link>

                <div class="mt-1 space-y-1.5">
                    <h1
                        class="text-2xl font-semibold tracking-tight text-balance"
                    >
                        {{ title }}
                    </h1>
                    <p
                        v-if="description"
                        class="text-sm text-pretty text-muted-foreground"
                    >
                        {{ description }}
                    </p>
                </div>
            </div>

            <!-- Form card -->
            <div
                class="rounded-2xl border border-border bg-card/80 p-6 shadow-sm backdrop-blur-sm sm:p-7"
            >
                <slot />
            </div>

            <p class="text-center text-xs text-muted-foreground">
                Secure temperature monitoring &middot; {{ name }}
            </p>
        </div>
    </div>
</template>
