<scroll-view class="w-full h-full bg-theme-background safe-area-top">
    <column class="w-full px-6 py-6 gap-6">
        <column class="w-full gap-3 p-5 bg-theme-surface rounded-3xl border border-theme-outline">
            <text class="text-xs font-semibold uppercase tracking-widest text-theme-primary">Pinkary</text>
            <text class="text-4xl font-extrabold text-theme-on-surface">A place for thoughtful questions and answers.</text>
            <text class="text-base leading-relaxed text-theme-on-surface-variant">Join the conversation, follow people you care about, and share what you are learning.</text>
        </column>

        <column class="w-full gap-3">
            <button variant="primary" @press="login">Log in</button>
            <button variant="secondary" @press="register">Create an account</button>
        </column>
    </column>
</scroll-view>
