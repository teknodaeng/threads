// PWA Install Logic
let deferredPrompt;
const installBtn = document.getElementById('installAppBtn');
const mobileInstallBtn = document.getElementById('mobileInstallAppBtn');

window.addEventListener('beforeinstallprompt', (e) => {
    // Prevent Chrome 67 and earlier from automatically showing the prompt
    e.preventDefault();
    // Stash the event so it can be triggered later.
    deferredPrompt = e;
    // Update UI to notify the user they can add to home screen
    if (installBtn) installBtn.classList.remove('hidden');
    if (mobileInstallBtn) mobileInstallBtn.classList.remove('hidden');
});

if (installBtn) {
    installBtn.addEventListener('click', (e) => {
        // hide our user interface that shows our A2HS button
        installBtn.classList.add('hidden');
        if (mobileInstallBtn) mobileInstallBtn.classList.add('hidden');
        // Show the prompt
        deferredPrompt.prompt();
        // Wait for the user to respond to the prompt
        deferredPrompt.userChoice.then((choiceResult) => {
            if (choiceResult.outcome === 'accepted') {
                console.log('User accepted the A2HS prompt');
            } else {
                console.log('User dismissed the A2HS prompt');
            }
            deferredPrompt = null;
        });
    });
}

if (mobileInstallBtn) {
    mobileInstallBtn.addEventListener('click', (e) => {
        // hide our user interface that shows our A2HS button
        if (installBtn) installBtn.classList.add('hidden');
        mobileInstallBtn.classList.add('hidden');
        // Show the prompt
        deferredPrompt.prompt();
        // Wait for the user to respond to the prompt
        deferredPrompt.userChoice.then((choiceResult) => {
            if (choiceResult.outcome === 'accepted') {
                console.log('User accepted the A2HS prompt');
            } else {
                console.log('User dismissed the A2HS prompt');
            }
            deferredPrompt = null;
        });
    });
}

window.addEventListener('appinstalled', (evt) => {
    // Log install to analytics
    console.log('INSTALL: Success');
});
