<?php

namespace App\Enums;

enum AffiliationType: string
{
    case Alumni = 'alumni';
    case Student = 'student';
    case FacultyStaff = 'faculty_staff';
    case PartnerGuest = 'partner_guest';
    case Other = 'other';
}
