@props([
    'contact',
    'productName' => 'Aureon CMS',
    'id' => 'aureonContactCard',
])

@php
    $emails = collect($contact['emails'] ?? []);
    $phones = collect($contact['phones'] ?? []);
    $quoteSubject = rawurlencode($productName.' implementation quote');
    $whatsappMessage = rawurlencode('Hello Meta Software Developers, I would like to discuss a '.$productName.' implementation.');
@endphp

<div class="modal fade aureon-contact-modal" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Title" aria-hidden="true" data-aureon-contact-card>
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <article class="modal-content aureon-contact-card">
            <header class="aureon-contact-card__header">
                <div class="aureon-contact-card__identity">
                    <img src="{{ asset('aureon/assets/brand/logo-light.png') }}" width="220" height="64" alt="Aureon Group">
                    <span>Implementation desk</span>
                </div>
                <button class="home-icon-button" type="button" data-bs-dismiss="modal" aria-label="Close contact details" title="Close"><i data-lucide="x" aria-hidden="true"></i></button>
            </header>

            <div class="modal-body aureon-contact-card__body">
                <section class="aureon-contact-card__intro" aria-labelledby="{{ $id }}Title">
                    <p class="home-eyebrow">Plan your implementation</p>
                    <h2 id="{{ $id }}Title">Let's shape the right {{ $productName }} setup.</h2>
                    <p>Tell us which modules, workflows, integrations, and deployment support your organization needs. We will follow up with a focused implementation proposal and costing.</p>
                    <div class="aureon-contact-card__company">
                        <strong>{{ $contact['name'] ?? 'META SOFTWARE DEVELOPERS' }}</strong>
                        <span>{{ $contact['tagline'] ?? '' }}</span>
                    </div>
                    <dl class="aureon-contact-card__meta">
                        <div><dt><i data-lucide="map-pin" aria-hidden="true"></i>Location</dt><dd>{{ $contact['location'] ?? '' }}</dd></div>
                        <div><dt><i data-lucide="globe-2" aria-hidden="true"></i>Website</dt><dd><a href="{{ $contact['website'] ?? '#' }}" target="_blank" rel="noopener noreferrer">{{ $contact['website_label'] ?? $contact['website'] ?? '' }}</a></dd></div>
                    </dl>
                </section>

                <section class="aureon-contact-card__channels" aria-label="Contact Meta Software Developers">
                    <div class="aureon-contact-card__group">
                        <h3>Email</h3>
                        @foreach ($emails as $email)
                            <a class="aureon-contact-card__row" href="mailto:{{ $email['address'] }}?subject={{ $quoteSubject }}">
                                <span class="aureon-contact-card__row-icon"><i data-lucide="mail" aria-hidden="true"></i></span>
                                <span><small>{{ $email['label'] }}</small><strong>{{ $email['address'] }}</strong></span>
                                <i data-lucide="arrow-up-right" aria-hidden="true"></i>
                            </a>
                        @endforeach
                    </div>

                    <div class="aureon-contact-card__group">
                        <h3>Call or WhatsApp</h3>
                        @foreach ($phones as $phone)
                            <div class="aureon-contact-card__phone">
                                <a class="aureon-contact-card__row" href="tel:{{ $phone['e164'] }}">
                                    <span class="aureon-contact-card__row-icon"><i data-lucide="phone" aria-hidden="true"></i></span>
                                    <span><small>{{ $phone['label'] }}</small><strong>{{ $phone['display'] }}</strong></span>
                                </a>
                                <a class="aureon-contact-card__whatsapp" href="https://wa.me/{{ $phone['whatsapp'] }}?text={{ $whatsappMessage }}" target="_blank" rel="noopener noreferrer" aria-label="Chat with Meta Software Developers on WhatsApp using {{ $phone['display'] }}">
                                    <i data-lucide="message-circle" aria-hidden="true"></i><span>WhatsApp</span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>

            <footer class="aureon-contact-card__footer">
                <span>{{ $contact['tagline'] ?? '' }}</span>
                <button type="button" data-bs-dismiss="modal">Close</button>
            </footer>
        </article>
    </div>
</div>
