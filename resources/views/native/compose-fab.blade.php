{{-- The web's floating button reads "Post" (aria-label/title);
    "Share an update" is its modal heading, not the button. --}}
<native:row class="w-full justify-end px-5 {{ ($appMenuOpen ?? false) ? 'opacity-0' : '' }}">
    <native:fab icon="edit" url="/compose" a11y-label="Post" />
</native:row>
