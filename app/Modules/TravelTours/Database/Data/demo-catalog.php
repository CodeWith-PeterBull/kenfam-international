<?php

/**
 * Curated fictional journeys for TravelTours storefront and browser QA.
 *
 * These records demonstrate module capabilities. They are not imported from,
 * nor represented as, the client's current products, departures, or prices.
 */

declare(strict_types=1);

return [
    [
        'code' => 'DEMO-EGY-01', 'slug' => 'cairo-and-the-nile-heritage-journey',
        'name' => 'Cairo and the Nile Heritage Journey',
        'tagline' => 'Ancient landmarks, river landscapes, and carefully paced discovery.',
        'summary' => 'Explore Cairo, Giza, and the Nile through an escorted itinerary balancing landmark visits with time to absorb each place.',
        'description' => "Begin among Cairo's historic quarters before meeting the monuments of Giza and following the Nile south. The journey combines guided interpretation, practical coordination, and unhurried evenings.",
        'type' => 'escorted', 'difficulty' => 'moderate', 'days' => 8, 'nights' => 7,
        'category' => ['Heritage Journeys', 'heritage-journeys', 'landmark'],
        'destination' => ['Egypt', 'egypt', 'EG', 'EG', 'Africa/Cairo', 30.0444200, 31.2357120],
        'image' => 'cairo-nile-heritage.webp', 'adult_minor' => 38500000, 'child_minor' => 29800000, 'capacity' => 24,
        'highlights' => ['Guided exploration of Cairo and Giza', 'A considered Nile journey with local interpretation', 'Balanced landmark visits and independent time'],
        'itinerary' => [['Arrival and Cairo orientation', 'Meet the local team, settle in, and review the journey ahead.'], ['Giza and the ancient plateau', 'Explore the major monuments with an expert local guide.'], ['Historic Cairo', 'Walk through layered districts, museums, and cultural spaces.'], ['The Nile southbound', 'Transfer into the river landscape and begin the next chapter.']],
    ],
    [
        'code' => 'DEMO-ZAF-01', 'slug' => 'cape-town-and-garden-route',
        'name' => 'Cape Town and Garden Route',
        'tagline' => 'Coastal scenery, city character, and South African hospitality.',
        'summary' => 'A relaxed escorted route through Cape Town and the Garden Route with coast, culture, and nature in balance.',
        'description' => "Discover Cape Town from mountain viewpoints to working neighbourhoods, then continue along one of Southern Africa's most rewarding coastal routes.",
        'type' => 'group', 'difficulty' => 'easy', 'days' => 7, 'nights' => 6,
        'category' => ['Scenic Escapes', 'scenic-escapes', 'mountain'],
        'destination' => ['South Africa', 'south-africa', 'ZA', 'ZA', 'Africa/Johannesburg', -33.9248685, 18.4240553],
        'image' => 'cape-town-garden-route.webp', 'adult_minor' => 32900000, 'child_minor' => 24900000, 'capacity' => 20,
        'highlights' => ['Cape Town city and peninsula perspectives', 'A scenic overland Garden Route journey', 'Local food, nature, and community encounters'],
        'itinerary' => [['Welcome to Cape Town', 'Arrival support and a calm city orientation.'], ['Peninsula landscapes', 'A full day following the coast and its communities.'], ['Into the Garden Route', 'Travel east with considered scenic stops.'], ['Forest and coast', 'Choose gentle nature walks and locally hosted experiences.']],
    ],
    [
        'code' => 'DEMO-UAE-01', 'slug' => 'dubai-city-and-desert',
        'name' => 'Dubai City and Desert',
        'tagline' => 'Contemporary city energy with a quieter desert horizon.',
        'summary' => 'A compact Dubai journey combining landmark districts, thoughtful free time, and an evening in the desert.',
        'description' => 'See the city through its historic creek, contemporary architecture, and diverse neighbourhoods before exchanging the skyline for an open desert evening.',
        'type' => 'private', 'difficulty' => 'easy', 'days' => 5, 'nights' => 4,
        'category' => ['City and Culture', 'city-and-culture', 'building-2'],
        'destination' => ['United Arab Emirates', 'united-arab-emirates', 'AE', 'AE', 'Asia/Dubai', 25.2048493, 55.2707828],
        'image' => 'dubai-city-desert.webp', 'adult_minor' => 21800000, 'child_minor' => 16500000, 'capacity' => 18,
        'highlights' => ['Old Dubai and the creek', 'Contemporary landmark districts', 'A hosted desert evening'],
        'itinerary' => [['Arrival and orientation', 'Private transfer and practical arrival briefing.'], ['Creek and old city', 'Explore trading districts and cultural landmarks.'], ['Contemporary Dubai', 'Architecture, viewpoints, and independent discovery.'], ['Desert evening', 'A relaxed afternoon and hosted desert experience.']],
    ],
    [
        'code' => 'DEMO-EUR-01', 'slug' => 'european-capitals-by-rail',
        'name' => 'European Capitals by Rail',
        'tagline' => 'Connected cities, landmark culture, and the pleasure of travelling by rail.',
        'summary' => 'An escorted multi-city journey using efficient rail connections and centrally planned stays.',
        'description' => 'Move between distinctive European capitals by rail, with guided introductions and independent time to follow personal interests.',
        'type' => 'escorted', 'difficulty' => 'moderate', 'days' => 10, 'nights' => 9,
        'category' => ['Rail Journeys', 'rail-journeys', 'train-front'],
        'destination' => ['Europe', 'europe', 'EU', null, 'Europe/Paris', 48.8566140, 2.3522219],
        'image' => 'european-capitals.webp', 'adult_minor' => 54800000, 'child_minor' => 41900000, 'capacity' => 22,
        'highlights' => ['Three contrasting capitals in one route', 'Reserved inter-city rail travel', 'Guided introductions and meaningful free time'],
        'itinerary' => [['First capital arrival', 'Arrival support and neighbourhood orientation.'], ['Landmarks and local context', 'A guided introduction followed by independent time.'], ['Rail to the next city', 'A coordinated station transfer and reserved rail journey.'], ['Culture at your pace', 'Choose museums, markets, or a hosted optional experience.']],
    ],
    [
        'code' => 'DEMO-HLY-01', 'slug' => 'holy-land-heritage-pilgrimage',
        'name' => 'Holy Land Heritage Pilgrimage',
        'tagline' => 'A reflective journey through places of enduring spiritual significance.',
        'summary' => 'A respectfully paced pilgrimage designed for faith communities, families, and private groups.',
        'description' => 'Travel through places central to faith and history with knowledgeable local guidance, space for reflection, and practical group coordination.',
        'type' => 'pilgrimage', 'difficulty' => 'moderate', 'days' => 9, 'nights' => 8,
        'category' => ['Faith and Pilgrimage', 'faith-and-pilgrimage', 'church'],
        'destination' => ['Holy Land', 'holy-land', 'HL', null, 'Asia/Jerusalem', 31.7683190, 35.2137100],
        'image' => 'holy-land-heritage.webp', 'adult_minor' => 46200000, 'child_minor' => 34800000, 'capacity' => 28,
        'highlights' => ['Guided faith and heritage interpretation', 'Daily space for reflection and fellowship', 'Experienced group coordination'],
        'itinerary' => [['Arrival and group welcome', 'Meet the local team and prepare for the pilgrimage.'], ['Ancient city perspectives', 'Walk significant quarters with guided interpretation.'], ['Places of reflection', 'A measured day of visits, context, and reflection.'], ['Community and landscape', 'Connect the region’s geography with its living communities.']],
    ],
    [
        'code' => 'DEMO-ASIA-01', 'slug' => 'singapore-and-kuala-lumpur',
        'name' => 'Singapore and Kuala Lumpur',
        'tagline' => 'Two dynamic cities connected through food, design, and shared histories.',
        'summary' => 'A polished twin-city journey pairing Singapore’s urban clarity with Kuala Lumpur’s layered character.',
        'description' => 'Experience two Southeast Asian cities through architecture, public spaces, food traditions, and neighbourhood stories.',
        'type' => 'educational', 'difficulty' => 'easy', 'days' => 7, 'nights' => 6,
        'category' => ['Learning Journeys', 'learning-journeys', 'graduation-cap'],
        'destination' => ['Southeast Asia', 'southeast-asia', 'SEA', null, 'Asia/Singapore', 1.3520830, 103.8198360],
        'image' => 'singapore-kuala-lumpur.webp', 'adult_minor' => 29800000, 'child_minor' => 22400000, 'capacity' => 20,
        'highlights' => ['Neighbourhood-led city introductions', 'A coordinated twin-city connection', 'Food, architecture, and public-space perspectives'],
        'itinerary' => [['Singapore arrival', 'Arrival support and practical city orientation.'], ['City in a garden', 'Explore civic, cultural, and garden districts.'], ['Neighbourhood stories', 'A hosted food and heritage walk with free time.'], ['Onward to Kuala Lumpur', 'Coordinated travel and evening orientation.']],
    ],
];
