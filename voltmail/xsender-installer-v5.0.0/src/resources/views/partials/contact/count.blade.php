
<td data-label="{{ translate('Group') }}">
     <div class="contact-count" data-group-id="{{ $contactGroup->id }}" data-href="{{ route("{$panel}.contact.index", $contactGroup->id) }}">
         <a href="{{ route("{$panel}.contact.index", $contactGroup->id) }}" class="badge badge--primary p-2">
             <span class="i-badge info-solid pill">
                 {{ translate("View All: ") }} {{ $contactGroup->contacts_count }} {{ translate(" contacts") }} <i class="ri-eye-line ms-1"></i>
             </span>
         </a>
     </div>
 </td>