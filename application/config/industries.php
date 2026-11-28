<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
  |--------------------------------------------------------------------------
  | Industries, and the products each one starts with
  |--------------------------------------------------------------------------
  |
  | Registering asks what the business does and, on the strength of that answer,
  | fills the item list with the things somebody in that trade actually bills
  | for. Typing "Ce" in a new invoice then suggests "Cement 50kg bag" instead of
  | an empty box.
  |
  | Two rules this file follows, because both decide whether the feature is
  | useful or annoying:
  |
  |  - It seeds, it does not sync. The rows are copied once, into the company's
  |    own `items`, and from that moment they belong to the account. Deleting
  |    "Cement 50kg bag" must stick: a list that refills itself is a list
  |    nobody bothers to curate. There is an explicit "restore" action for
  |    anyone who wants the starter set back.
  |  - Every entry is a title and a unit, never a price. A wrong price printed
  |    on an invoice is a business problem; a blank one is a prompt.
  |
  | To add a trade: copy a block below, give it a key, a label and its products.
  | `quantity_type` must be one of the four units the schema ships with -
  | kilogram, number, days, months (see quantity_types in database/import).
  | Anything else is coerced to 'number' by the seeder, so a typo here cannot
  | break a registration.
  |
 */
$config['industries'] = array(

    'general' => array(
        'label'    => 'General trade / other',
        'blurb'    => 'A little of everything - goods, services, delivery.',
        'products' => array(
            array('Goods supplied',                    'number'),
            array('Consultation',                      'days'),
            array('Delivery',                          'number'),
            array('Installation',                      'days'),
            array('Repair and maintenance',            'days'),
        ),
    ),

    'construction' => array(
        'label'    => 'Construction and hardware',
        'blurb'    => 'Materials, labour, transport to site.',
        'products' => array(
            array('Cement 50kg bag',                   'number'),
            array('Sand',                              'number'),
            array('Ballast',                           'number'),
            array('Steel rebar Y12',                   'number'),
            array('Binding wire 1kg roll',             'number'),
            array('Roofing sheet 30g',                 'number'),
            array('Timber 4x2',                        'number'),
            array('Nails 1kg',                         'kilogram'),
            array('Masonry labour',                    'days'),
            array('Transport to site',                 'number'),
        ),
    ),

    'retail' => array(
        'label'    => 'Retail and wholesale',
        'blurb'    => 'Stock lines, packaging, delivery.',
        'products' => array(
            array('Item - assorted stock',             'number'),
            array('Packaging',                         'number'),
            array('Delivery within town',              'number'),
            array('Bulk discount line',                'number'),
        ),
    ),

    'hospitality' => array(
        'label'    => 'Hospitality and food',
        'blurb'    => 'Meals, catering, venue and staff time.',
        'products' => array(
            array('Breakfast',                         'number'),
            array('Lunch',                             'number'),
            array('Dinner',                            'number'),
            array('Conference package',                'days'),
            array('Accommodation',                     'days'),
            array('Outside catering',                  'number'),
            array('Beverages',                         'number'),
            array('Service staff',                     'days'),
        ),
    ),

    'transport' => array(
        'label'    => 'Transport and logistics',
        'blurb'    => 'Trips, tonnage, waiting time.',
        'products' => array(
            array('Local delivery',                    'number'),
            array('Upcountry delivery',                'number'),
            array('Loading and offloading',            'number'),
            array('Waiting time',                      'days'),
            array('Fuel surcharge',                    'number'),
            array('Goods in transit cover',            'number'),
        ),
    ),

    'professional' => array(
        'label'    => 'Professional services',
        'blurb'    => 'Time, retainers, filings.',
        'products' => array(
            array('Consultation',                      'days'),
            array('Retainer - monthly',                'months'),
            array('Site visit',                        'number'),
            array('Report and filing',                 'number'),
            array('Training session',                  'days'),
            array('Disbursements',                     'number'),
        ),
    ),

    'technology' => array(
        'label'    => 'Technology and IT',
        'blurb'    => 'Devices, licences, support and setup.',
        'products' => array(
            array('Laptop',                            'number'),
            array('Desktop',                           'number'),
            array('Software licence',                  'number'),
            array('Network installation',              'number'),
            array('Support - monthly',                 'months'),
            array('On-site support',                   'days'),
            array('Data recovery',                     'number'),
            array('Cabling per point',                 'number'),
        ),
    ),

    'health' => array(
        'label'    => 'Health and pharmacy',
        'blurb'    => 'Consumables, consultations, procedures.',
        'products' => array(
            array('Consultation',                      'number'),
            array('Procedure',                         'number'),
            array('Lab test',                          'number'),
            array('Medicine dispensed',                'number'),
            array('Dressing',                          'number'),
            array('Home visit',                        'number'),
        ),
    ),

    'education' => array(
        'label'    => 'Education and training',
        'blurb'    => 'Terms, tuition, materials, exams.',
        'products' => array(
            array('Tuition - term',                    'months'),
            array('Tuition - month',                   'months'),
            array('Registration fee',                  'number'),
            array('Exam fee',                          'number'),
            array('Learning materials',                'number'),
            array('Transport',                         'months'),
            array('Boarding',                          'months'),
        ),
    ),

    'agriculture' => array(
        'label'    => 'Agriculture and livestock',
        'blurb'    => 'Inputs, produce, feed and labour.',
        'products' => array(
            array('Seed',                              'kilogram'),
            array('Fertiliser 50kg',                   'number'),
            array('Pesticide',                         'kilogram'),
            array('Animal feed',                       'number'),
            array('Produce - grade A',                 'kilogram'),
            array('Produce - grade B',                 'kilogram'),
            array('Farm labour',                       'days'),
            array('Transport',                         'number'),
        ),
    ),

    'manufacturing' => array(
        'label'    => 'Manufacturing and workshop',
        'blurb'    => 'Raw material, machining, finishing.',
        'products' => array(
            array('Raw material',                      'kilogram'),
            array('Machining',                         'days'),
            array('Wielding and join',                 'days'),
            array('Painting and finish',               'number'),
            array('Assembly',                          'number'),
            array('Quality inspection',                'number'),
            array('Packaging',                         'number'),
        ),
    ),

    'creative' => array(
        'label'    => 'Creative, media and design',
        'blurb'    => 'Projects, days, licensing.',
        'products' => array(
            array('Design - logo',                     'number'),
            array('Design - print',                    'number'),
            array('Photography',                       'days'),
            array('Videography',                       'days'),
            array('Editing',                           'days'),
            array('Social media - month',              'months'),
            array('Licensing',                         'number'),
        ),
    ),
);

/*
 * A short, ordered list for the registration form's dropdown. The first entry
 * is the default: a form that is submitted without an answer must still land
 * somewhere sensible, and 'general' is the honest answer when we do not know.
 */
$config['industries_default'] = 'general';
