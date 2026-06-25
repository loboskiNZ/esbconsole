import QRCode from 'qrcode';

export function studioBandInvites(invites = [], createUrl = '') {
    return {
        copiedId: null,
        showCreateForm: invites.length === 0,

        init() {
            this.$nextTick(() => {
                this.renderAllQrs();
            });
        },

        async renderAllQrs() {
            for (const invite of invites) {
                const canvas = this.$root.querySelector(`[data-invite-qr="${invite.id}"]`);

                if (!canvas || !invite.invite_url) {
                    continue;
                }

                await QRCode.toCanvas(canvas, invite.invite_url, {
                    width: 168,
                    margin: 1,
                    errorCorrectionLevel: 'M',
                });
            }
        },

        async copyUrl(url, id) {
            const copied = await this.writeClipboard(url);

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

        downloadQr(id, filename) {
            const canvas = this.$root.querySelector(`[data-invite-qr="${id}"]`);

            if (!canvas) {
                return;
            }

            canvas.toBlob((blob) => {
                if (!blob) {
                    return;
                }

                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = filename || `band-invite-${id}.png`;
                link.click();
                URL.revokeObjectURL(url);
            }, 'image/png');
        },
    };
}
