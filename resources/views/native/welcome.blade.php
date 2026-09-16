<native:scroll-view class="w-full h-full bg-theme-background safe-area">
    <native:column class="w-full min-h-full justify-center px-6 py-8 gap-6">
        <native:column class="w-full gap-3">
            <native:text class="text-5xl font-extrabold text-theme-primary">Pinkary</native:text>
            <native:text class="text-xl font-semibold text-theme-on-background">A place for thoughtful questions and answers.</native:text>
            <native:text class="text-base leading-relaxed text-theme-on-surface-variant">Join the conversation, follow people you care about, and share what you are learning.</native:text>
        </native:column>

        <native:column class="w-full gap-3">
            <native:button variant="primary" @tap="login">Log in</native:button>
            <native:button variant="secondary" @tap="register">Create an account</native:button>
        </native:column>
    </native:column>
</native:scroll-view>
