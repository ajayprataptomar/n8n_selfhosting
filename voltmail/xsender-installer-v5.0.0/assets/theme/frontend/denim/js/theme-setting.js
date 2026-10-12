(function () {
    "use strict"
    // HTML Root Element
    const rootHtml = document.firstElementChild;

    function setElementAttribute(element, attribute, value) {
        element.setAttribute(attribute, value);
    }

    // Default localstroag settings
    let siteData = {
        lang: "en",
        dir: "ltr",
        dataTheme: "light",
    };

    const setLocalStorageData = (siteData) => {
        localStorage.setItem("siteData", JSON.stringify(siteData))
    };

    function getLocalStorageData() {
        const siteDataJSON = localStorage.getItem("siteData");
        return siteDataJSON ? JSON.parse(siteDataJSON) : { lang: "en", dir: "ltr", dataTheme: "light" };
    }

    // LTR & RTL Features Start
    function addCSS(cssFile) {
        const cssLink = document.querySelector("#bootstrap-css");

        if (!cssLink) {
            const newCssLink = document.createElement("link");
            newCssLink.rel = "stylesheet";
            newCssLink.type = "text/css";
            newCssLink.href = cssFile;
            newCssLink.id = "bootstrap-css";
            document.head.appendChild(newCssLink);
        } else {
            cssLink.href = cssFile;
        }
    }

    function setDirection(dirMode) {
        // rootHtml.setAttribute("dir", dirMode);
        // Use the CSS paths provided by the theme manager
        const cssFile = dirMode === "rtl" ? window.cssPathsConfig.rtl : window.cssPathsConfig.ltr;
        addCSS(cssFile);

        setTimeout(() => {
            window.dispatchEvent(new Event("resize"));
        }, 100);
    }

    function handleDirection() {
        const currentDir = rootHtml.getAttribute("dir") || "ltr";
        const dirMode = currentDir === "ltr" ? "rtl" : "ltr";

        const activeIcon = document.querySelector('#direction-switcher > i');
        if (activeIcon) {
            activeIcon.className = dirMode === "rtl" ? "bi bi-filter-right" : "bi bi-filter-left";
        }

        setDirection(dirMode);
        const currentData = { ...getLocalStorageData(), dir: dirMode };
        setLocalStorageData(currentData);
    }

    const storedData = getLocalStorageData();
    setDirection(storedData.dir);

    const switcher = document.querySelector('#direction-switcher');
    if (switcher) {
        switcher.addEventListener('click', handleDirection);
    }


    // Theme Features Start
    const handelTheme = (e) => {
        const currentAttribute = rootHtml.getAttribute("data-bs-theme");
        const newAttribute = currentAttribute === 'light'
            ? 'dark'
            : 'light';
        setElementAttribute(rootHtml, "data-bs-theme", newAttribute);

        const activeIcon = document.querySelector('#theme-toggle > i');
        if (activeIcon) {
            // Update active icon based on theme
            activeIcon.className = newAttribute === 'dark' ? ' bi-brightness-high' : 'bi-moon-fill';
        }

        setPreference(newAttribute);
    };

    const getColorPreference = () => {
        const storedData = getLocalStorageData();
        if (storedData) {
            return storedData;
        } else {
            return window.matchMedia('(prefers-color-scheme: dark)').matches
                ? 'dark'
                : 'light';
        }
    };

    const setPreference = (theme) => {
        const currentData = { ...getLocalStorageData(), dataTheme: theme };
        setLocalStorageData(currentData);
        reflectPreference(theme);
    };

    const reflectPreference = (theme) => {
        rootHtml.setAttribute("data-bs-theme", theme);
        document
            .querySelector('#theme-toggle')
            ?.setAttribute('aria-label', theme);
    };


    const themeToggle = document.querySelector('#theme-toggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', handelTheme);
    }


    // sync with system changes
    window.matchMedia('(prefers-color-scheme: dark)')
        .addEventListener('change', ({ matches: isDark }) => {
            const currentTheme = isDark ? 'dark' : 'light';
            setPreference(currentTheme);
        });

    const isSelectedTheme = () => {
        const storedData = getLocalStorageData();
        if (storedData) {
            const currentTheme = storedData.dataTheme;
            rootHtml.setAttribute("data-bs-theme", currentTheme);

            const activeIcon = document.querySelector('#theme-toggle > i');
            if (activeIcon) {
                activeIcon.className = currentTheme === 'dark' ? ' bi-brightness-high' : 'bi-moon-fill';
            }
        }

    }

    if (document.readyState === 'loading') {
        window.addEventListener('DOMContentLoaded',
            isSelectedTheme
        )
    }
}())