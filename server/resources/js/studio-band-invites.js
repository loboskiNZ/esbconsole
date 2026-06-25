export function studioBandInvites() {
    return {
        copiedId: null,

        async copyUrl(url, id) {
            const copied = await this.writeClipboard(url);

            if (copied) {
                this.flashCopied(id);
            }
        },

        async copySlug(slug, id) {
            const copied = await this.writeClipboard(slug);

            if (copied) {
                this.flashCopied(id);
            }
        },

        async writeClipboard(value) {
            if (!value) {
                return false;
            }

            try {
                await navigator.clipboard.writeText(value);

                return true;
            } catch {
                return false;
            }
        },

        flashCopied(id) {
            this.copiedId = id;

            window.setTimeout(() => {
                if (this.copiedId === id) {
                    this.copiedId = null;
                }
            }, 2000);
        },
    };
}
