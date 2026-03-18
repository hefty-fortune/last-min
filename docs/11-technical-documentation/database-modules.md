# Database modules

```text
  Module                   Collections              Attributes
  Authentication Module User                        name, last name, phone, mail
                           Sessions                 sessions token and infos
  Provider                 Ogranization             name, address, oib, service type
                           Profile                  name, email, role
                           Locations                name, address, service type


  Slot                     Slot                     loc_rel, service_type_rel, state, price, capacity, datetime, description, tags


  Booking                  Booking                  slot_rel, user_rel, payment_rel, status, expires_at
                           Rezevation_locks         lock_id, locked_until, locked_by, slot_rel, booking_rel
                           Payment                  booking_rel, user_rel, status,
                           Booking_confirmation confirmation_id, booking_rel, confirmed_at, status


  Cancellation             Cancellation             booking_rel, reason


  feeback                  Reliabilty               count_sum_slots_created, count_slots_cancelled, count_slots_completed, loc_rel
                           Rating                   slot_rel, feedback_text, rating


  Admin                    TBD


  Notifications            -
```
