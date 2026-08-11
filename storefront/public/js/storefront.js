document.addEventListener('DOMContentLoaded', () => {
    const initializeNavigation = () => {
        const links = Array.from(
            document.querySelectorAll(
                '[data-nav-section]',
            ),
        );

        const sections = links
            .map((link) => {
                const id =
                    link.dataset.navSection;

                return {
                    id,
                    link,
                    section:
                        document.getElementById(
                            id,
                        ),
                };
            })
            .filter(
                (item) => item.section,
            );

        if (
            sections.length === 0
            || !(
                'IntersectionObserver'
                in window
            )
        ) {
            return;
        }

        const setActive = (id) => {
            links.forEach((link) => {
                link.classList.toggle(
                    'is-active',
                    link.dataset
                        .navSection === id,
                );
            });
        };

        const observer =
            new IntersectionObserver(
                (entries) => {
                    const visible =
                        entries
                            .filter(
                                (entry) =>
                                    entry
                                        .isIntersecting,
                            )
                            .sort(
                                (
                                    left,
                                    right,
                                ) =>
                                    right
                                        .intersectionRatio
                                    - left
                                        .intersectionRatio,
                            );

                    if (
                        visible.length === 0
                    ) {
                        return;
                    }

                    setActive(
                        visible[0]
                            .target
                            .id,
                    );
                },
                {
                    rootMargin:
                        '-20% 0px -65% 0px',
                    threshold: [
                        0.05,
                        0.2,
                        0.5,
                    ],
                },
            );

        sections.forEach((item) => {
            observer.observe(
                item.section,
            );
        });
    };

    const initializeCarousel = (
        carousel,
    ) => {
        if (
            carousel.dataset
                .carouselInitialized
            === 'true'
        ) {
            return;
        }

        carousel.dataset
            .carouselInitialized =
            'true';

        const track =
            carousel.querySelector(
                '[data-carousel-track]',
            );

        const slides = Array.from(
            carousel.querySelectorAll(
                '[data-carousel-slide]',
            ),
        );

        const thumbnails =
            Array.from(
                carousel.querySelectorAll(
                    '[data-carousel-thumbnail]',
                ),
            );

        const previous =
            carousel.querySelector(
                '[data-carousel-previous]',
            );

        const next =
            carousel.querySelector(
                '[data-carousel-next]',
            );

        const counter =
            carousel.querySelector(
                '[data-carousel-counter]',
            );

        if (
            !track
            || slides.length === 0
        ) {
            return;
        }

        let currentIndex = 0;
        let visibleSlideIndexes = slides.map((_, index) => index);
        let touchStartX = null;

        const normalizeIndex = (
            requestedIndex,
        ) => {
            const total =
                visibleSlideIndexes.length || slides.length;

            return (
                (
                    requestedIndex
                    % total
                )
                + total
            ) % total;
        };

        const visiblePositionForIndex = (
            slideIndex,
        ) => Math.max(
            0,
            visibleSlideIndexes.indexOf(
                slideIndex,
            ),
        );

        const syncVisibleOrder = () => {
            slides.forEach((slide, slideIndex) => {
                const visiblePosition =
                    visibleSlideIndexes.indexOf(slideIndex);

                slide.style.order =
                    visiblePosition >= 0
                        ? String(visiblePosition)
                        : String(slides.length + slideIndex);
            });
        };

        const showSlide = (
            requestedIndex,
        ) => {
            syncVisibleOrder();

            const visiblePosition =
                normalizeIndex(requestedIndex);
            currentIndex =
                visibleSlideIndexes[
                    visiblePosition
                ] ?? 0;

            track.style.transform =
                `translate3d(-${
                    visiblePosition * 100
                }%, 0, 0)`;

            slides.forEach(
                (
                    slide,
                    slideIndex,
                ) => {
                    const active =
                        slideIndex
                        === currentIndex;
                    const visible =
                        visibleSlideIndexes
                            .includes(slideIndex);

                    slide.hidden = !visible;

                    slide.setAttribute(
                        'aria-hidden',
                        active && visible
                            ? 'false'
                            : 'true',
                    );
                },
            );

            thumbnails.forEach(
                (
                    thumbnail,
                    thumbnailIndex,
                ) => {
                    const active =
                        thumbnailIndex
                        === currentIndex;

                    thumbnail
                        .classList
                        .toggle(
                            'is-active',
                            active,
                        );

                    thumbnail
                        .setAttribute(
                            'aria-current',
                            active
                                ? 'true'
                                : 'false',
                        );

                    thumbnail.hidden =
                        !visibleSlideIndexes
                            .includes(
                                thumbnailIndex,
                            );
                },
            );

            if (counter) {
                counter.textContent =
                    `${
                        visiblePosition + 1
                    } / ${visibleSlideIndexes.length || slides.length}`;
            }

            const activeThumbnail =
                thumbnails[
                    currentIndex
                ];

            if (activeThumbnail) {
                activeThumbnail
                    .scrollIntoView({
                        behavior:
                            'smooth',
                        block:
                            'nearest',
                        inline:
                            'nearest',
                    });
            }
        };

        const showSlideForVariant = (
            detail,
        ) => {
            const selectedColor =
                String(detail?.color || '');
            const selectedBattery =
                String(detail?.battery_ah || '');

            if (!selectedColor) {
                return;
            }

            const normalize =
                (value) =>
                    String(value || '')
                        .trim()
                        .toLowerCase();

            const matchingIndexes =
                slides
                    .map((slide, index) => ({
                        slide,
                        index,
                    }))
                    .filter(
                        ({ slide }) =>
                            normalize(
                                slide.dataset
                                    .carouselColor,
                            )
                            === normalize(
                                selectedColor,
                            )
                            && (
                                !selectedBattery
                                || !slide.dataset
                                    .carouselBattery
                                || String(
                                    slide.dataset
                                        .carouselBattery,
                                )
                                    === selectedBattery
                            ),
                    )
                    .map(({ index }) => index);

            visibleSlideIndexes =
                matchingIndexes.length > 0
                    ? matchingIndexes
                    : slides.map((_, index) => index);

            showSlide(0);
        };

        const showAdjacentSlide = (
            direction,
        ) => {
            showSlide(
                visiblePositionForIndex(
                    currentIndex,
                ) + direction,
            );
        };

        const showThumbnailSlide = (
            requestedIndex,
        ) => {
            const requestedSlideIndex =
                Number.parseInt(
                    String(requestedIndex ?? '0'),
                    10,
                );
            const visiblePosition =
                visiblePositionForIndex(
                    requestedSlideIndex,
                );

            showSlide(visiblePosition);
        };

        previous?.addEventListener(
            'click',
            () => {
                showAdjacentSlide(-1);
            },
        );

        next?.addEventListener(
            'click',
            () => {
                showAdjacentSlide(1);
            },
        );

        thumbnails.forEach(
            (thumbnail) => {
                thumbnail
                    .addEventListener(
                        'click',
                        () => {
                            const index =
                                Number.parseInt(
                                    thumbnail
                                        .dataset
                                        .carouselThumbnail
                                    ?? '0',
                                    10,
                                );

                            showThumbnailSlide(index);
                        },
                    );
            },
        );

        carousel.addEventListener(
            'keydown',
            (event) => {
                if (
                    event.key
                    === 'ArrowLeft'
                ) {
                    event.preventDefault();

                    showAdjacentSlide(-1);
                }

                if (
                    event.key
                    === 'ArrowRight'
                ) {
                    event.preventDefault();

                    showAdjacentSlide(1);
                }
            },
        );

        carousel.addEventListener(
            'touchstart',
            (event) => {
                touchStartX =
                    event.touches[0]
                        ?.clientX
                    ?? null;
            },
            {
                passive: true,
            },
        );

        carousel.addEventListener(
            'touchend',
            (event) => {
                if (
                    touchStartX === null
                ) {
                    return;
                }

                const touchEndX =
                    event.changedTouches[0]
                        ?.clientX
                    ?? touchStartX;

                const distance =
                    touchEndX
                    - touchStartX;

                touchStartX = null;

                if (
                    Math.abs(distance)
                    < 45
                ) {
                    return;
                }

                showAdjacentSlide(
                    distance > 0 ? -1 : 1,
                );
            },
            {
                passive: true,
            },
        );

        carousel.addEventListener(
            'storefront:variant-selected',
            (event) => {
                showSlideForVariant(
                    event.detail || {},
                );
            },
        );

        showSlide(0);
    };

    const initializeCarousels = (
        root = document,
    ) => {
        root
            .querySelectorAll(
                '[data-model-carousel]',
            )
            .forEach(
                initializeCarousel,
            );
    };

    const initializeHomeGallery = (gallery) => {
        if (gallery.dataset.homeGalleryInitialized === 'true') return;
        gallery.dataset.homeGalleryInitialized = 'true';

        const track = gallery.querySelector('[data-home-gallery-track]');
        const slides = Array.from(gallery.querySelectorAll('.legacy-home-gallery__slide'));
        const previous = gallery.querySelector('[data-home-gallery-prev]');
        const next = gallery.querySelector('[data-home-gallery-next]');
        const dots = gallery.querySelector('[data-home-gallery-dots]');

        if (!track || slides.length === 0) return;

        let index = 0;

        const renderDots = () => {
            if (!dots) return;
            dots.innerHTML = '';
            slides.forEach((_, slideIndex) => {
                const dot = document.createElement('button');
                dot.type = 'button';
                dot.className = 'legacy-home-gallery__dot';
                dot.setAttribute('aria-label', `Ver foto ${slideIndex + 1}`);
                dot.addEventListener('click', () => show(slideIndex));
                dots.appendChild(dot);
            });
        };

        const show = (requestedIndex) => {
            index = ((requestedIndex % slides.length) + slides.length) % slides.length;
            track.style.transform = `translate3d(-${index * 100}%, 0, 0)`;
            slides.forEach((slide, slideIndex) => {
                slide.setAttribute('aria-hidden', slideIndex === index ? 'false' : 'true');
            });
            dots?.querySelectorAll('.legacy-home-gallery__dot').forEach((dot, dotIndex) => {
                dot.classList.toggle('is-active', dotIndex === index);
                dot.setAttribute('aria-current', dotIndex === index ? 'true' : 'false');
            });
        };

        previous?.addEventListener('click', () => show(index - 1));
        next?.addEventListener('click', () => show(index + 1));

        if (slides.length <= 1) {
            previous?.setAttribute('hidden', 'hidden');
            next?.setAttribute('hidden', 'hidden');
            dots?.setAttribute('hidden', 'hidden');
        }

        renderDots();
        show(0);
    };

    const initializeHomeGalleries = (root = document) => {
        root.querySelectorAll('[data-home-gallery]').forEach(initializeHomeGallery);
    };

    const initializeVariantSelector = (form) => {
        if (form.dataset.variantInitialized === 'true') return;
        form.dataset.variantInitialized = 'true';

        let variants = [];
        try {
            variants = JSON.parse(form.dataset.variantOptions || '[]');
        } catch {
            variants = [];
        }

        const color = form.querySelector('[data-variant-color]');
        const battery = form.querySelector('[data-variant-battery]');
        const id = form.querySelector('[data-variant-id]');
        const submit = form.querySelector('[data-variant-submit]');
        const status = form.querySelector('[data-variant-status]');
        const scope = form.closest('[data-model-card]')
            || form.closest('.model-detail__grid')
            || form.closest('.model-detail__content')
            || form;
        const price = scope.querySelector('[data-variant-price]');
        const currency = scope.querySelector('[data-variant-currency]');
        const colorOptions = Array.from(
            scope.querySelectorAll('[data-variant-color-option]'),
        );

        const quantity = (variant) => Number(variant?.availability?.quantity || 0);
        const isAvailable = (variant) => quantity(variant) > 0;
        const sameColor = (variant, value) => String(variant?.color || '') === String(value || '');
        const sameBattery = (variant, value) => Number(variant?.battery_ah || 0) === Number(value || 0);
        const availableVariants = () => variants.filter(isAvailable);

        const updatePrice = (variant) => {
            if (!variant || !price) return;
            const code = String(variant.price?.currency || 'UYU').toUpperCase();
            const amount = Number(variant.price?.gross || 0);
            price.textContent = new Intl.NumberFormat('es-UY', {
                style: 'currency',
                currency: code,
                maximumFractionDigits: 0,
            }).format(amount);
            if (currency) currency.textContent = code;
        };

        const updateOptions = (chosen) => {
            const live = availableVariants();

            if (color) {
                Array.from(color.options).forEach((option) => {
                    option.disabled = !live.some((variant) => sameColor(variant, option.value));
                });
            }

            colorOptions.forEach((option) => {
                const value = option.dataset.variantColorOption || '';
                const enabled = live.some((variant) => sameColor(variant, value));
                const active = sameColor(chosen, value);

                option.disabled = !enabled;
                option.classList.toggle('is-active', active);
                option.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            if (battery) {
                const selectedColor = chosen?.color ?? color?.value ?? '';
                Array.from(battery.options).forEach((option) => {
                    option.disabled = !live.some((variant) => (
                        (!color || sameColor(variant, selectedColor))
                        && sameBattery(variant, option.value)
                    ));
                });
            }
        };

        const choose = (source = 'initial') => {
            const selectedColor = color?.value || '';
            const selectedBattery = battery?.value || '';
            const live = availableVariants();
            let chosen = null;

            if (source === 'color' && color) {
                chosen = live.find((variant) => (
                    sameColor(variant, selectedColor)
                    && (!battery || sameBattery(variant, selectedBattery))
                )) || live.find((variant) => sameColor(variant, selectedColor));
            } else if (source === 'battery' && battery) {
                chosen = live.find((variant) => (
                    (!color || sameColor(variant, selectedColor))
                    && sameBattery(variant, selectedBattery)
                )) || live.find((variant) => sameBattery(variant, selectedBattery));
            } else {
                chosen = live.find((variant) => (
                    (!color || sameColor(variant, selectedColor))
                    && (!battery || sameBattery(variant, selectedBattery))
                )) || live[0];
            }

            if (!chosen) {
                chosen = variants.find((variant) => (
                    (!color || sameColor(variant, selectedColor))
                    && (!battery || sameBattery(variant, selectedBattery))
                )) || variants[0] || null;
            }

            if (chosen && color) color.value = String(chosen.color || '');
            if (chosen && battery) battery.value = String(chosen.battery_ah || '');
            updateOptions(chosen);

            const available = chosen && isAvailable(chosen);
            if (id) id.value = available ? String(chosen.variant_id || '') : '';
            if (submit) submit.disabled = !available;
            updatePrice(chosen);

            const carousel = scope.querySelector('[data-model-carousel]');
            carousel?.dispatchEvent(new CustomEvent('storefront:variant-selected', {
                detail: {
                    color: chosen?.color || '',
                    battery_ah: chosen?.battery_ah || '',
                    variant_id: chosen?.variant_id || '',
                },
            }));

            if (status) {
                status.textContent = available
                    ? `Disponible · ${quantity(chosen)} unidad${quantity(chosen) === 1 ? '' : 'es'}`
                    : 'Esta combinación está agotada o no disponible.';
                status.classList.toggle('is-unavailable', !available);
            }
        };

        color?.addEventListener('change', () => choose('color'));
        colorOptions.forEach((option) => {
            option.addEventListener('click', () => {
                if (!color || option.disabled) return;
                color.value = option.dataset.variantColorOption || '';
                choose('color');
            });
        });
        battery?.addEventListener('change', () => choose('battery'));
        form.addEventListener('submit', (event) => {
            if (!id?.value || submit?.disabled) {
                event.preventDefault();
                choose('initial');
            }
        });

        choose('initial');
    };

    const initializeVariantSelectors = (root = document) => root.querySelectorAll('[data-variant-selector]').forEach(initializeVariantSelector);

    initializeNavigation();
    initializeCarousels();
    initializeHomeGalleries();
    initializeVariantSelectors();

    const observer =
        new MutationObserver(
            (mutations) => {
                mutations.forEach(
                    (mutation) => {
                        mutation
                            .addedNodes
                            .forEach(
                                (node) => {
                                    if (
                                        !(
                                            node
                                            instanceof
                                            Element
                                        )
                                    ) {
                                        return;
                                    }

                                    if (
                                        node.matches(
                                            '[data-model-carousel]',
                                        )
                                    ) {
                                        initializeCarousel(
                                            node,
                                        );
                                    }

                                    initializeCarousels(
                                        node,
                                    );
                                    initializeHomeGalleries(
                                        node,
                                    );
                                    initializeVariantSelectors(node);
                                },
                            );
                    },
                );
            },
        );

    observer.observe(
        document.body,
        {
            childList: true,
            subtree: true,
        },
    );
});
