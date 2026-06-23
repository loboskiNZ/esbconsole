const prefersReducedMotion = () =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const delay = (ms) =>
    new Promise((resolve) => window.setTimeout(resolve, ms));

const motionDelay = (ms) => (prefersReducedMotion() ? 0 : ms);

export function portalLanding(restoreUsername = '', loginFailed = false) {
    const restoredUsername = typeof restoreUsername === 'string' ? restoreUsername : '';

    return {
        bgLoaded: false,
        bgVisible: false,
        overlayVisible: false,
        logoVisible: false,
        welcomeVisible: false,
        welcomeSettled: false,
        loginVisible: false,
        loginStep: restoredUsername !== '' ? 'password' : 'username',
        username: restoredUsername,
        password: '',
        showLoginButton: restoredUsername !== '' && loginFailed,
        showForgotPassword: restoredUsername !== '' && loginFailed,

        init() {
            const image = this.$refs.backgroundImage;

            if (!image) {
                return;
            }

            if (image.complete && image.naturalWidth > 0) {
                this.onBackgroundLoaded();
            }
        },

        async onBackgroundLoaded() {
            if (this.bgLoaded) {
                return;
            }

            this.bgLoaded = true;
            this.bgVisible = true;

            await delay(motionDelay(1200));
            this.overlayVisible = true;

            await delay(motionDelay(900));
            this.logoVisible = true;

            await delay(motionDelay(1500));
            this.welcomeVisible = true;

            await delay(motionDelay(1800));
            this.welcomeSettled = true;

            await delay(motionDelay(1200));
            this.loginVisible = true;
        },

        continueFromUsername() {
            if (!this.username.trim()) {
                return;
            }

            this.loginStep = 'password';
            this.showLoginButton = false;
            this.showForgotPassword = false;
        },

        onPasswordInput() {
            const hasPassword = this.password.length > 0;

            if (hasPassword && !this.showLoginButton) {
                this.showLoginButton = true;

                window.setTimeout(() => {
                    if (this.password.length > 0) {
                        this.showForgotPassword = true;
                    }
                }, motionDelay(450));
            }

            if (!hasPassword) {
                this.showLoginButton = false;
                this.showForgotPassword = false;
            }
        },

        submitLogin(event) {
            if (this.loginStep === 'username') {
                event.preventDefault();
                this.continueFromUsername();
                return;
            }

            if (! this.username.trim() || ! this.password) {
                event.preventDefault();
            }
        },
    };
}
